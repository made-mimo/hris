<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security fix: no response carried any of the standard defense-in-depth
 * headers. A Content-Security-Policy is deliberately left out here — this
 * app relies on inline `style="..."` attributes and Livewire's own inline
 * hydration `<script>` payloads throughout, and a CSP strict enough to be
 * meaningful would need a proper nonce/hash rollout and real regression
 * testing to add without breaking pages, not a same-pass addition. The four
 * headers below carry no such compatibility risk.
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

        return $response;
    }
}
