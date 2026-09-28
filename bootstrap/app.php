<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsurePasswordPolicyMet;
use App\Http\Middleware\EnsureScreenAccess;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Support\ApiEnvelope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'two_factor' => EnsureTwoFactorVerified::class,
            'screen' => EnsureScreenAccess::class,
            'password_policy' => EnsurePasswordPolicyMet::class,
            // Spec F6/A1: gates a Sanctum token's *scope*, not just its
            // validity — the short-lived `2fa-pending` token login() issues
            // to a 2FA-required user carries only the `2fa:verify` ability,
            // so `abilities:*` (required by every substantive API route)
            // rejects it everywhere except the one endpoint that ability
            // grants.
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        $middleware->append(AddSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Spec Section 3.2's REST API framework: "consistent error envelopes
        // (400 validation, 403 authorization, 404 not found)" — applied only
        // to api/* requests so the existing Livewire web app's own error
        // pages are untouched.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(ApiEnvelope::error(400, 'The given data was invalid.', $e->errors()), 400);
            }
        });

        // Spec F6 expanded this surface from 4 routes behind auth:sanctum to
        // 39 — an expired/missing/revoked token is now a routine, expected
        // client-facing case, not a corner one, so it gets the same envelope
        // as every other error rather than Laravel's default bare
        // {"message": "Unauthenticated."} shape.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(ApiEnvelope::error(401, 'Unauthenticated.'), 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(ApiEnvelope::error(403, $e->getMessage() ?: 'This action is unauthorized.'), 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(ApiEnvelope::error(404, 'The requested resource was not found.'), 404);
            }
        });

        // Catches abort()/abort_if()/abort_unless() generically — those raise
        // a plain HttpException (not AuthorizationException/ModelNotFoundException
        // above), which is how every Api\*Controller in this app actually
        // signals 403/404/etc., so this is the handler that matters most.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() >= 400) {
                return response()->json(
                    ApiEnvelope::error($e->getStatusCode(), $e->getMessage() ?: 'An error occurred.'),
                    $e->getStatusCode()
                );
            }
        });
    })->create();
