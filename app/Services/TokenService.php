<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Jetons JWT d'accès et de rafraîchissement, renvoyés dans le corps des réponses
 * (pas de cookies : l'application mobile les garde dans son stockage sécurisé).
 * La version de jeton de l'utilisateur (`token_version`) est embarquée : l'incrémenter
 * révoque tous les jetons déjà délivrés.
 */
class TokenService
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Réponse de connexion : jetons, profil, charte de l'entreprise et menus (espace web).
     */
    public function issue(User $user): array
    {
        $user->loadMissing(['role', 'tenant']);
        $now = time();
        $accessTtl = (int) config('jwt.access_ttl', 3600);

        $access = $this->encode([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $accessTtl,
            'sub' => $user->id,
            'ver' => $user->token_version,
            'jti' => 'access_' . bin2hex(random_bytes(8)),
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'role' => $user->role?->name,
                'tenant_id' => $user->tenant_id,
            ],
            'token_type' => 'access',
        ]);

        $refresh = $this->encode([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + (int) config('jwt.refresh_ttl', 2592000),
            'sub' => $user->id,
            'ver' => $user->token_version,
            'jti' => 'refresh_' . bin2hex(random_bytes(8)),
            'token_type' => 'refresh',
        ]);

        return [
            'step' => 'authenticated',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'must_change_password' => (bool) $user->must_change_password,
            'user' => $this->profile($user),
            'menus' => Menu::query()
                ->whereHas('menuRoles', fn ($q) => $q->where('role_id', $user->role_id))
                ->orderBy('position')
                ->get(),
        ];
    }

    /** Profil de l'utilisateur, avec la charte et l'état d'abonnement de son entreprise. */
    public function profile(User $user): array
    {
        $user->loadMissing(['role', 'tenant']);

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone_one,
            'phone_display' => \App\Support\SenegalPhone::format($user->phone_one),
            'phone_two' => $user->phone_two,
            'address' => $user->address,
            'role' => $user->role?->name,
            'tenant' => $user->tenant?->branding(),
            'subscription' => $user->tenant ? $this->subscriptions->statusForTenant($user->tenant) : null,
        ];
    }

    /**
     * Décode un jeton et vérifie son type et sa version ; null s'il n'est plus valable.
     *
     * @return array{0: User, 1: object}|null
     */
    public function resolve(string $token, string $type): ?array
    {
        try {
            $payload = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        if (($payload->token_type ?? '') !== $type) {
            return null;
        }

        $user = User::with(['role', 'tenant'])->find($payload->sub ?? 0);
        if (!$user || (int) ($payload->ver ?? -1) !== (int) $user->token_version) {
            return null;
        }

        return [$user, $payload];
    }

    private function encode(array $payload): string
    {
        $issuer = config('app.url') . '/api';

        return JWT::encode(['iss' => $issuer, 'aud' => $issuer] + $payload, config('jwt.secret'), 'HS256');
    }
}
