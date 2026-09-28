<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security fix: no response carried any of the standard defense-in-depth
 * headers. The Content-Security-Policy this docblock originally deferred
 * ("would need a proper nonce/hash rollout") now ships separately — see
 * AddContentSecurityPolicy — report-only first, per PIM/HRIS alignment §3C
 * item 4. HSTS below is item 3's other half; only sent over an actually
 * secure connection, so a plain-HTTP local/dev request is never told to
 * upgrade.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
