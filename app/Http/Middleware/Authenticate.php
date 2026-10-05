<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // Pour les routes API, ne jamais rediriger → lance AuthenticationException proprement
        return null;
    }
}
