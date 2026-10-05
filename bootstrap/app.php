<?php

use App\Helpers\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn() => null);
        // Derrière le Nginx de l'hôte (HTTPS) puis celui du conteneur : schéma et IP client réels
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'jwt.auth'     => \App\Http\Middleware\JwtAuthenticate::class,
            'role'         => \App\Http\Middleware\EnsureRole::class,
            'subscription' => \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force Laravel à traiter TOUTES les requêtes API comme JSON
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*');
        });

        // Les erreurs portent un code stable (error_code) exploité par les applications
        $exceptions->render(function (ValidationException $e, Request $request) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?? 'Données invalides.',
                422,
                $e->errors(),
                'VALIDATION_ERROR',
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return ApiResponse::error('Non authentifié.', 401, null, 'UNAUTHENTICATED');
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return ApiResponse::error('Action non autorisée.', 403, null, 'FORBIDDEN');
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return ApiResponse::error('Trop de requêtes. Patientez un instant.', 429, null, 'TOO_MANY_REQUESTS');
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $message = $e->getPrevious() instanceof ModelNotFoundException ? 'Élément introuvable.' : ($e->getMessage() ?: 'Ressource introuvable.');

            return ApiResponse::error($message, 404, null, 'NOT_FOUND');
        });

        // abort(422, '...') dans les services
        $exceptions->render(function (HttpException $e, Request $request) {
            return ApiResponse::error($e->getMessage() ?: 'Erreur.', $e->getStatusCode());
        });
    })
    ->create();
