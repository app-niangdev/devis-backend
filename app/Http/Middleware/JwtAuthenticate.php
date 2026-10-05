<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JwtAuthenticate
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return ApiResponse::error('Token manquant.', 401, null, 'TOKEN_MISSING');
        }

        // Jeton expiré, d'un autre type, ou révoqué (token_version incrémenté)
        $resolved = $this->tokens->resolve($token, 'access');
        if (! $resolved) {
            return ApiResponse::error('Token invalide ou expiré.', 401, null, 'TOKEN_INVALID');
        }

        [$user] = $resolved;

        if (! $user->status) {
            return ApiResponse::error(
                'Votre compte est désactivé. Contactez l\'administrateur de la plateforme.',
                403,
                null,
                'ACCOUNT_DISABLED',
            );
        }

        // Injecte l'utilisateur dans la requête (compatible avec $request->user())
        Auth::setUser($user);

        return $next($request);
    }
}
