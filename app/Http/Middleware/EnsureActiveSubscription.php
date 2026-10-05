<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;

/**
 * Coupe l'accès d'un gestionnaire dont l'entreprise est désactivée ou n'est plus couverte
 * par un abonnement, y compris pour une session ouverte avant l'échéance.
 */
class EnsureActiveSubscription
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user?->isManager()) {
            $this->subscriptions->ensureAccessible($user->tenant);
        }

        return $next($request);
    }
}
