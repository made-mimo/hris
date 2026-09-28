<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PIM/HRIS alignment §3C item 4 — report-only first (config/security.php's
 * CSP_MODE), so a real violation surfaces in the log via CspReportController
 * before anything is actually blocked. 'unsafe-inline'/'unsafe-eval' on
 * script-src are required by Livewire/Alpine's own inline hydration
 * payloads and event handlers, not a shortcut taken here — tightening that
 * further would need a nonce/hash rollout Livewire doesn't support out of
 * the box. fonts.googleapis.com/gstatic.com are this app's one external
 * resource host (⚡layouts/guest.blade.php and ⚡layouts/app.blade.php's
 * Google Fonts `<link>`) — there is no external avatar service or other
 * third-party host to account for; every other visual (initials, icons) is
 * drawn locally.
 */
class AddContentSecurityPolicy
{
    private const POLICY = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."font-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com data:; "
        ."img-src 'self' data: blob:; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'none'; "
        .'report-uri /api/csp-report';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $header = match (config('security.csp_mode', 'report-only')) {
            'enforce' => 'Content-Security-Policy',
            'report-only' => 'Content-Security-Policy-Report-Only',
            default => null,
        };

        if ($header) {
            $response->headers->set($header, self::POLICY);
        }

        return $response;
    }
}
