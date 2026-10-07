<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\SenegalPhone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Codes à usage unique envoyés sur WhatsApp (WAHA) : première connexion, mot de passe oublié,
 * confirmation d'un numéro modifié par l'administrateur et inscription depuis l'application.
 *
 * Le code n'est jamais renvoyé au client ni stocké en clair. Après un code valide, les parcours
 * qui se terminent par un nouveau mot de passe reçoivent un jeton de réinitialisation de courte durée.
 */
class OtpService
{
    /** Envois maximum par utilisateur et par heure, tous parcours confondus. */
    private const MAX_SENDS_PER_HOUR = 6;

    public function __construct(private readonly WahaService $waha)
    {
    }

    /**
     * Ouvre un nouveau défi (le précédent du même parcours est annulé) et envoie le code.
     *
     * @throws ApiException
     */
    public function start(User $user, string $purpose, ?string $ip = null): OtpChallenge
    {
        $this->throttleSends($user);

        OtpChallenge::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->newCode();
        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'challenge_token' => Str::random(64),
            'code_hash' => Hash::make($code),
            'ip_address' => $ip,
            'last_sent_at' => now(),
            'expires_at' => now()->addSeconds($this->ttl()),
        ]);

        $this->deliver($user, $challenge, $code);

        return $challenge;
    }

    /**
     * Vérifie le code. Renvoie le jeton de réinitialisation pour les parcours « mot de passe »,
     * null pour la simple confirmation du numéro.
     *
     * @throws ApiException
     */
    public function verify(string $challengeToken, string $code): array
    {
        $challenge = $this->pending($challengeToken);

        if ($challenge->expires_at->isPast()) {
            throw new ApiException('Ce code a expiré. Demandez un nouveau code.', 'OTP_EXPIRED');
        }

        $maxAttempts = (int) config('otp.max_attempts', 5);
        if ($challenge->attempts >= $maxAttempts) {
            throw new ApiException('Trop d\'essais. Demandez un nouveau code.', 'OTP_TOO_MANY_ATTEMPTS', 429);
        }

        if (!Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');
            $left = $maxAttempts - $challenge->attempts;

            throw new ApiException(
                $left > 0
                    ? "Code incorrect. Il vous reste {$left} essai" . ($left > 1 ? 's' : '') . '.'
                    : 'Code incorrect. Demandez un nouveau code.',
                $left > 0 ? 'OTP_INVALID' : 'OTP_TOO_MANY_ATTEMPTS',
                $left > 0 ? 422 : 429,
                ['attempts_left' => $left],
            );
        }

        $resetToken = null;
        $attributes = ['verified_at' => now()];

        if ($challenge->requiresPassword()) {
            $resetToken = Str::random(64);
            $attributes += [
                'reset_token_hash' => hash('sha256', $resetToken),
                'reset_expires_at' => now()->addSeconds((int) config('otp.reset_ttl', 600)),
            ];
        } else {
            $attributes['consumed_at'] = now();
        }

        $challenge->update($attributes);

        return ['challenge' => $challenge, 'reset_token' => $resetToken];
    }

    /**
     * Renvoie un nouveau code pour le même défi (délai minimal et nombre de renvois limités).
     *
     * @throws ApiException
     */
    public function resend(string $challengeToken): OtpChallenge
    {
        $challenge = $this->pending($challengeToken);

        if ($challenge->resend_count >= (int) config('otp.max_resend', 3)) {
            throw new ApiException(
                'Nombre maximal de renvois atteint. Recommencez depuis le début.',
                'OTP_RESEND_LIMIT',
                429,
            );
        }

        $wait = $this->resendWait($challenge);
        if ($wait > 0) {
            throw new ApiException(
                "Patientez {$wait} s avant de demander un nouveau code.",
                'OTP_RESEND_TOO_SOON',
                429,
                ['resend_in' => $wait],
            );
        }

        $this->throttleSends($challenge->user);

        $code = $this->newCode();
        $challenge->update([
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'resend_count' => $challenge->resend_count + 1,
            'last_sent_at' => now(),
            'expires_at' => now()->addSeconds($this->ttl()),
        ]);

        $this->deliver($challenge->user, $challenge, $code);

        return $challenge;
    }

    /**
     * Consomme le jeton de réinitialisation remis après un code valide.
     *
     * @throws ApiException
     */
    public function consumeResetToken(string $resetToken): OtpChallenge
    {
        $challenge = OtpChallenge::with('user.role', 'user.tenant')
            ->where('reset_token_hash', hash('sha256', $resetToken))
            ->whereNull('consumed_at')
            ->first();

        if (!$challenge || !$challenge->reset_expires_at || $challenge->reset_expires_at->isPast() || !$challenge->user) {
            throw new ApiException(
                'Cette demande a expiré. Recommencez la procédure.',
                'RESET_TOKEN_INVALID',
            );
        }

        $challenge->update(['consumed_at' => now()]);

        return $challenge;
    }

    /** Informations utiles à l'écran de saisie du code. */
    public function describe(OtpChallenge $challenge): array
    {
        return [
            'step' => 'otp_required',
            'purpose' => $challenge->purpose,
            'challenge_token' => $challenge->challenge_token,
            'phone_masked' => SenegalPhone::mask($challenge->user?->phone_one),
            'expires_in' => max(0, (int) now()->diffInSeconds($challenge->expires_at, false)),
            'resend_in' => $this->resendWait($challenge),
            'resends_left' => max(0, (int) config('otp.max_resend', 3) - $challenge->resend_count),
        ];
    }

    /**
     * Réponse factice pour un numéro inconnu (mot de passe oublié) : même forme qu'un vrai défi,
     * pour ne pas révéler quels numéros sont inscrits.
     */
    public function decoy(string $phone): array
    {
        return [
            'step' => 'otp_required',
            'purpose' => OtpChallenge::PASSWORD_RESET,
            'challenge_token' => Str::random(64),
            'phone_masked' => SenegalPhone::mask($phone),
            'expires_in' => $this->ttl(),
            'resend_in' => (int) config('otp.resend_cooldown', 60),
            'resends_left' => (int) config('otp.max_resend', 3),
        ];
    }

    private function pending(string $challengeToken): OtpChallenge
    {
        $challenge = OtpChallenge::with('user')
            ->where('challenge_token', $challengeToken)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->first();

        if (!$challenge || !$challenge->user) {
            throw new ApiException('Cette demande a expiré. Recommencez la procédure.', 'OTP_EXPIRED');
        }

        return $challenge;
    }

    private function deliver(User $user, OtpChallenge $challenge, string $code): void
    {
        $minutes = (int) ceil($this->ttl() / 60);
        $app = config('otp.app_name');
        $text = match ($challenge->purpose) {
            OtpChallenge::FIRST_LOGIN => "*{$app}* : votre code d'activation est *{$code}*.\nValable {$minutes} min. Ne le communiquez à personne.",
            OtpChallenge::SIGNUP => "*{$app}* : votre code de confirmation d'inscription est *{$code}*.\nValable {$minutes} min. Ne le communiquez à personne.",
            OtpChallenge::PASSWORD_RESET => "*{$app}* : votre code de réinitialisation du mot de passe est *{$code}*.\nValable {$minutes} min. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            default => "*{$app}* : votre code de confirmation est *{$code}*.\nValable {$minutes} min. Ne le communiquez à personne.",
        };

        $chatId = SenegalPhone::chatId($user->phone_one);

        if (!$this->waha->isConfigured()) {
            if (app()->environment(['local', 'testing'])) {
                // Développement sans WAHA : le code est lisible dans storage/logs
                Log::info('OTP (WAHA non configuré)', ['user_id' => $user->id, 'purpose' => $challenge->purpose, 'code' => $code]);

                return;
            }

            $this->failDelivery($challenge, 'WAHA non configuré');
        }

        try {
            $this->waha->sendText((string) $chatId, $text);
            Log::info('OTP envoyé', ['user_id' => $user->id, 'purpose' => $challenge->purpose]);
        } catch (\Throwable $e) {
            $this->failDelivery($challenge, $e->getMessage());
        }
    }

    private function failDelivery(OtpChallenge $challenge, string $reason): never
    {
        Log::error('Envoi OTP impossible', ['user_id' => $challenge->user_id, 'purpose' => $challenge->purpose, 'error' => $reason]);
        $challenge->update(['consumed_at' => now()]);

        throw new ApiException(
            'Impossible d\'envoyer le code sur WhatsApp pour le moment. Réessayez dans quelques minutes.',
            'OTP_SEND_FAILED',
            503,
        );
    }

    private function throttleSends(User $user): void
    {
        $key = 'otp-send:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_SENDS_PER_HOUR)) {
            throw new ApiException(
                'Trop de codes demandés. Réessayez dans ' . (int) ceil(RateLimiter::availableIn($key) / 60) . ' min.',
                'OTP_RATE_LIMITED',
                429,
            );
        }

        RateLimiter::hit($key, 3600);
    }

    private function resendWait(OtpChallenge $challenge): int
    {
        $cooldown = (int) config('otp.resend_cooldown', 60);
        $elapsed = $challenge->last_sent_at ? (int) $challenge->last_sent_at->diffInSeconds(now()) : $cooldown;

        return max(0, $cooldown - $elapsed);
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function ttl(): int
    {
        return (int) config('otp.ttl', 300);
    }
}
