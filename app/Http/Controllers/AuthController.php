<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Helpers\ApiResponse;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Mail\ResetPasswordMail;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SignupService;
use App\Services\SubscriptionService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Authentification.
 *
 * Gestionnaire : téléphone + mot de passe. Un code OTP WhatsApp n'est demandé qu'à la première
 * connexion (puis choix du mot de passe), après un changement de numéro par l'administrateur,
 * et pour le mot de passe oublié. Administrateur : e-mail + mot de passe.
 *
 * Inscription depuis l'application : le code WhatsApp confirme le numéro,
 * active l'entreprise (période d'essai) et connecte l'artisan.
 */
class AuthController extends Controller
{
    /** Échecs de mot de passe tolérés par identifiant avant blocage temporaire. */
    private const MAX_FAILED_LOGINS = 5;

    public function __construct(
        private readonly TokenService $tokens,
        private readonly OtpService $otp,
        private readonly SubscriptionService $subscriptions,
        private readonly SignupService $signups,
    ) {}

    // -------------------------------------------------------------------------
    // REGISTER (artisan, depuis l'application)
    // -------------------------------------------------------------------------
    public function register(RegisterRequest $request): JsonResponse
    {
        $challenge = $this->signups->register($request->validated(), $request->ip());

        return ApiResponse::success(
            $this->otp->describe($challenge),
            'Un code de confirmation vous a été envoyé sur WhatsApp.',
            201,
        );
    }

    // -------------------------------------------------------------------------
    // LOGIN
    // -------------------------------------------------------------------------
    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->filled('phone') ? $request->input('phone') : strtolower((string) $request->input('email'));
        $throttleKey = 'login:' . $identifier;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_LOGINS)) {
            throw new ApiException(
                'Trop de tentatives. Réessayez dans ' . (int) ceil(RateLimiter::availableIn($throttleKey) / 60) . ' min.',
                'TOO_MANY_ATTEMPTS',
                429,
            );
        }

        $user = User::with(['role', 'tenant'])
            ->where($request->filled('phone') ? 'phone_one' : 'email', $identifier)
            ->first();

        if (!$user || !Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 900);

            throw new ApiException(
                $request->filled('phone') ? 'Numéro ou mot de passe incorrect.' : 'E-mail ou mot de passe incorrect.',
                'INVALID_CREDENTIALS',
                401,
            );
        }

        RateLimiter::clear($throttleKey);

        if ($user->isManager() && $user->tenant?->isPending()) {
            // Inscription interrompue avant la saisie du code : on renvoie un code pour la terminer
            if (!$user->phone_verified_at) {
                return $this->otpStep($this->otp->start($user, OtpChallenge::SIGNUP, $request->ip()));
            }

            // Numéro déjà confirmé (inscription faite quand l'administrateur validait les comptes)
            $this->signups->activate($user->tenant);
            $user->load('tenant');
        }

        $this->ensureCanSignIn($user);

        if ($user->isManager()) {
            // Première connexion : code WhatsApp puis choix du mot de passe, avant tout jeton
            if ($user->must_change_password) {
                return $this->otpStep($this->otp->start($user, OtpChallenge::FIRST_LOGIN, $request->ip()));
            }

            // Numéro modifié par l'administrateur : il doit être confirmé
            if (!$user->phone_verified_at) {
                return $this->otpStep($this->otp->start($user, OtpChallenge::PHONE_VERIFICATION, $request->ip()));
            }
        }

        return ApiResponse::success($this->tokens->issue($user), 'Connexion réussie.');
    }

    // -------------------------------------------------------------------------
    // OTP
    // -------------------------------------------------------------------------
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->otp->verify($request->input('challenge_token'), $request->input('code'));

        if ($result['reset_token']) {
            return ApiResponse::success([
                'step' => 'set_password',
                'purpose' => $result['challenge']->purpose,
                'reset_token' => $result['reset_token'],
                'expires_in' => (int) config('otp.reset_ttl', 600),
            ], 'Code vérifié. Choisissez votre nouveau mot de passe.');
        }

        $user = $result['challenge']->user;
        $user->update(['phone_verified_at' => now()]);

        // Inscription : numéro confirmé, l'entreprise est activée avec sa période d'essai
        if ($result['challenge']->purpose === OtpChallenge::SIGNUP && $user->tenant) {
            $this->signups->activate($user->tenant);
            $user->load('tenant');
            $this->ensureCanSignIn($user);

            return ApiResponse::success($this->tokens->issue($user), 'Numéro confirmé. Bienvenue sur SN Devis !');
        }

        // Confirmation du numéro : la connexion se termine
        $this->ensureCanSignIn($user);

        return ApiResponse::success($this->tokens->issue($user), 'Numéro confirmé. Connexion réussie.');
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate(['challenge_token' => ['required', 'string', 'size:64']], [
            'challenge_token.*' => 'La demande est introuvable. Recommencez la procédure.',
        ]);

        $challenge = $this->otp->resend($request->input('challenge_token'));

        return ApiResponse::success($this->otp->describe($challenge), 'Un nouveau code vous a été envoyé sur WhatsApp.');
    }

    /**
     * Nouveau mot de passe après un code valide : première connexion ou mot de passe oublié.
     * Toutes les sessions existantes sont révoquées, puis l'utilisateur est connecté.
     */
    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        $challenge = $this->otp->consumeResetToken($request->input('reset_token'));
        $user = $challenge->user;

        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
            // Le code reçu sur WhatsApp prouve la possession du numéro
            'phone_verified_at' => now(),
            'token_version' => $user->token_version + 1,
        ])->save();

        // Inscription interrompue puis « mot de passe oublié » : le code reçu confirme aussi l'inscription
        if ($user->tenant?->isPending()) {
            $this->signups->activate($user->tenant);
            $user->load('tenant');
        }

        $this->ensureCanSignIn($user);

        return ApiResponse::success(
            $this->tokens->issue($user),
            $challenge->purpose === OtpChallenge::FIRST_LOGIN
                ? 'Mot de passe enregistré. Bienvenue !'
                : 'Mot de passe réinitialisé avec succès.'
        );
    }

    // -------------------------------------------------------------------------
    // FORGOT PASSWORD
    // -------------------------------------------------------------------------
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Gestionnaire : code sur WhatsApp. Même réponse que le numéro soit inscrit ou non.
        if ($request->filled('phone')) {
            $phone = $request->input('phone');
            $user = User::with('role')->where('phone_one', $phone)->first();

            $payload = $user && $user->status && $user->isManager()
                ? $this->otp->describe($this->otp->start($user, OtpChallenge::PASSWORD_RESET, $request->ip()))
                : $this->otp->decoy($phone);

            return ApiResponse::success($payload, 'Si ce numéro est inscrit, un code vous a été envoyé sur WhatsApp.');
        }

        // Administrateur : code par e-mail
        $user = User::where('email', $request->input('email'))->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );

            Mail::to($user->email)->send(new ResetPasswordMail($user, $code));
        }

        return ApiResponse::success(null, 'Si cette adresse existe, un code de vérification a été envoyé.');
    }

    // -------------------------------------------------------------------------
    // RESET PASSWORD (administrateur : code reçu par e-mail + nouveau mot de passe)
    // -------------------------------------------------------------------------
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (! $record || ! Hash::check($request->input('code'), $record->token)) {
            return ApiResponse::error('Code de vérification invalide.', 422, null, 'RESET_CODE_INVALID');
        }

        if (now()->diffInMinutes($record->created_at, true) > 15) {
            return ApiResponse::error('Ce code a expiré. Veuillez en demander un nouveau.', 422, null, 'RESET_CODE_EXPIRED');
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return ApiResponse::error('Utilisateur introuvable.', 404);
        }

        $user->forceFill([
            'password'             => $request->input('password'),
            'must_change_password' => false,
            'token_version'        => $user->token_version + 1,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return ApiResponse::success(null, 'Mot de passe réinitialisé avec succès.');
    }

    // -------------------------------------------------------------------------
    // REFRESH
    // -------------------------------------------------------------------------
    public function refresh(Request $request): JsonResponse
    {
        $resolved = $request->bearerToken() ? $this->tokens->resolve($request->bearerToken(), 'refresh') : null;

        if (! $resolved) {
            return ApiResponse::error('Session expirée. Veuillez vous reconnecter.', 401, null, 'TOKEN_INVALID');
        }

        [$user] = $resolved;
        $this->ensureCanSignIn($user);

        return ApiResponse::success($this->tokens->issue($user), 'Session renouvelée.');
    }

    // -------------------------------------------------------------------------
    // LOGOUT
    // -------------------------------------------------------------------------
    public function logout(): JsonResponse
    {
        // Jetons sans état : l'application les supprime. La révocation globale passe par token_version.
        return ApiResponse::success(null, 'Déconnexion réussie.');
    }

    // -------------------------------------------------------------------------
    // ME
    // -------------------------------------------------------------------------
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->tokens->profile($request->user()));
    }

    // -------------------------------------------------------------------------
    // UPDATE MY PROFILE (téléphone secondaire, adresse, mot de passe)
    // -------------------------------------------------------------------------
    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->only(['phone_two', 'address']);

        if ($request->filled('password')) {
            if (! Hash::check($request->input('current_password'), $user->password)) {
                return ApiResponse::error('Le mot de passe actuel est incorrect.', 422, null, 'CURRENT_PASSWORD_INVALID');
            }

            $data['password'] = $request->input('password');
        }

        $user->update($data);

        return ApiResponse::success($this->tokens->profile($user->fresh()), 'Profil mis à jour avec succès.');
    }

    // -------------------------------------------------------------------------
    // CHANGE PASSWORD (première connexion de l'administrateur)
    // -------------------------------------------------------------------------
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->must_change_password || ! $user->isAdmin()) {
            return ApiResponse::error('Aucun changement de mot de passe requis.', 403);
        }

        $user->update([
            'password'             => $request->input('password'),
            'must_change_password' => false,
        ]);

        return ApiResponse::success(null, 'Mot de passe mis à jour avec succès.');
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    /**
     * Compte actif ; pour un gestionnaire, entreprise active et abonnement valide.
     *
     * @throws ApiException
     */
    private function ensureCanSignIn(User $user): void
    {
        if (! $user->status) {
            throw new ApiException(
                'Votre compte est désactivé. Contactez l\'administrateur de la plateforme.',
                'ACCOUNT_DISABLED',
                403,
            );
        }

        if ($user->isManager()) {
            $this->subscriptions->ensureAccessible($user->tenant);
        }
    }

    private function otpStep(OtpChallenge $challenge): JsonResponse
    {
        return ApiResponse::success(
            $this->otp->describe($challenge),
            'Un code de vérification vous a été envoyé sur WhatsApp.',
        );
    }
}
