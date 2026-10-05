<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Restreint une route à un ou plusieurs rôles : `role:ADMIN`, `role:MANAGER`.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! in_array($request->user()?->role?->name, $roles, true)) {
            return ApiResponse::error('Action non autorisée.', 403, null, 'FORBIDDEN');
        }

        return $next($request);
    }
}
