<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SenegalPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inscription d'un artisan depuis l'application mobile.
 *
 * 1. Formulaire : entreprise « en attente » et compte gestionnaire, puis code WhatsApp.
 * 2. Code valide : numéro confirmé, la demande apparaît chez l'administrateur.
 * 3. L'administrateur valide (période d'essai offerte à partir de ce jour) ou refuse ;
 *    l'artisan est prévenu sur WhatsApp.
 */
class SignupService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly SubscriptionService $subscriptions,
        private readonly WahaService $waha,
    ) {}

    /**
     * @throws ValidationException|ApiException
     */
    public function register(array $data, ?string $ip = null): OtpChallenge
    {
        $this->releaseAbandonedSignup($data['phone']);
        $this->ensureWhatsapp($data['phone']);

        $user = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'trade' => $data['trade'] ?? null,
                'code_website' => $this->uniqueCode($data['company_name']),
                'phone_call' => $data['phone'],
                'phone_whatsapp' => $data['phone'],
                'approval_status' => Tenant::PENDING,
            ]);

            return User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone_one' => $data['phone'],
                'password' => $data['password'],
                'status' => true,
                'role_id' => Role::where('name', 'MANAGER')->value('id'),
                'tenant_id' => $tenant->id,
                'must_change_password' => false,
                'phone_verified_at' => null,
            ]);
        });

        return $this->otp->start($user, OtpChallenge::SIGNUP, $ip);
    }

    /**
     * Ouvre l'accès : période d'essai à partir d'aujourd'hui, puis message WhatsApp.
     *
     * @throws ApiException
     */
    public function approve(Tenant $tenant, User $admin): Tenant
    {
        $this->ensurePending($tenant);
        $manager = $this->manager($tenant);

        if (!$manager?->phone_verified_at) {
            throw new ApiException(
                'Le numéro de cet artisan n\'est pas encore confirmé : il doit d\'abord saisir le code reçu sur WhatsApp.',
                'PHONE_NOT_VERIFIED',
                422,
            );
        }

        DB::transaction(function () use ($tenant, $admin) {
            $tenant->update([
                'approval_status' => Tenant::APPROVED,
                'approval_reviewed_at' => now(),
                'approval_reviewed_by' => $admin->id,
                'rejection_reason' => null,
            ]);
            $this->subscriptions->createTrial($tenant, $admin->id);
        });

        $days = (int) config('subscriptions.trial_days', 30);
        $this->notify($manager, "Bonne nouvelle {$manager->first_name} ! Votre compte *{$this->appName()}* est activé"
            . ($days > 0 ? " avec {$days} jours d'essai gratuits" : '') . ".\n"
            . 'Connectez-vous dans l\'application avec votre numéro et votre mot de passe.');

        return $tenant->fresh();
    }

    /**
     * @throws ApiException
     */
    public function reject(Tenant $tenant, User $admin, ?string $reason): Tenant
    {
        $this->ensurePending($tenant);

        $tenant->update([
            'approval_status' => Tenant::REJECTED,
            'approval_reviewed_at' => now(),
            'approval_reviewed_by' => $admin->id,
            'rejection_reason' => $reason,
        ]);

        if ($manager = $this->manager($tenant)) {
            $this->notify($manager, "Bonjour {$manager->first_name}, votre demande d'inscription à *{$this->appName()}* n'a pas été retenue."
                . ($reason ? "\nMotif : {$reason}" : ''));
        }

        return $tenant->fresh();
    }

    /**
     * Un numéro déjà pris ne peut pas s'inscrire, sauf une inscription abandonnée
     * avant la saisie du code : elle est effacée pour repartir de zéro.
     *
     * @throws ValidationException
     */
    private function releaseAbandonedSignup(string $phone): void
    {
        $existing = User::with('tenant')->where('phone_one', $phone)->first();
        if (!$existing) {
            return;
        }

        $tenant = $existing->tenant;

        if ($tenant?->isPending() && !$existing->phone_verified_at) {
            DB::transaction(function () use ($existing, $tenant) {
                $existing->forceDelete();
                $tenant->forceDelete();
            });

            return;
        }

        throw ValidationException::withMessages(['phone' => match (true) {
            (bool) $tenant?->isPending() => 'Une demande d\'inscription est déjà en cours de validation pour ce numéro.',
            (bool) $tenant?->isRejected() => 'La demande d\'inscription de ce numéro a été refusée. Contactez SN Devis pour plus d\'informations.',
            default => 'Ce numéro est déjà inscrit. Connectez-vous ou utilisez « Mot de passe oublié ».',
        }]);
    }

    /**
     * Les codes partent sur WhatsApp : le numéro doit y avoir un compte.
     * Si WAHA ne peut pas répondre, on ne bloque pas l'inscription.
     *
     * @throws ValidationException
     */
    private function ensureWhatsapp(string $phone): void
    {
        if ($this->waha->numberExists((string) SenegalPhone::chatId($phone)) === false) {
            throw ValidationException::withMessages([
                'phone' => 'Ce numéro n\'a pas de compte WhatsApp : vous ne pourriez pas recevoir votre code de confirmation.',
            ]);
        }
    }

    /** @throws ApiException */
    private function ensurePending(Tenant $tenant): void
    {
        if (!$tenant->isPending()) {
            throw new ApiException('Cette inscription a déjà été traitée.', 'SIGNUP_ALREADY_REVIEWED', 422);
        }
    }

    private function manager(Tenant $tenant): ?User
    {
        return $tenant->users()->with('role')->get()->first(fn (User $user) => $user->isManager());
    }

    /** Code unique de l'entreprise, dérivé de son nom (« menuiserie-diop-k3f9 »). */
    private function uniqueCode(string $name): string
    {
        $base = Str::limit(Str::slug($name), 40, '') ?: 'entreprise';

        do {
            $code = $base . '-' . Str::lower(Str::random(4));
        } while (Tenant::withTrashed()->where('code_website', $code)->exists());

        return $code;
    }

    /** Message d'information : un échec d'envoi ne doit pas annuler la décision de l'administrateur. */
    private function notify(User $user, string $text): void
    {
        if (!$this->waha->isConfigured()) {
            Log::info('Message WhatsApp non envoyé (WAHA non configuré)', ['user_id' => $user->id, 'text' => $text]);

            return;
        }

        try {
            $this->waha->sendText((string) SenegalPhone::chatId($user->phone_one), $text);
        } catch (\Throwable $e) {
            Log::warning('Message WhatsApp d\'inscription non envoyé', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function appName(): string
    {
        return (string) config('otp.app_name');
    }
}
