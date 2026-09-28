<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * PIM/HRIS alignment §3C item 4's CSP report-uri target — see
 * App\Http\Middleware\AddContentSecurityPolicy. Sits in the api route group,
 * unauthenticated and outside CSRF, since a browser sends these reports with
 * no session and no way to attach a CSRF token. Browsers post the body as
 * `application/csp-report` (or `application/reports+json`), neither of
 * which Laravel's request parsing treats as JSON automatically, so the raw
 * body is decoded by hand. Only a trimmed summary is logged — the full
 * report can include the page's query string, which may carry data this
 * app has no other reason to persist to a log file.
 */
class CspReportController extends ApiController
{
    public function store(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $report = $payload['csp-report'] ?? $payload['body'] ?? [];

        if (is_array($report) && $report !== []) {
            Log::warning('CSP violation reported', [
                'document_uri' => $report['document-uri'] ?? $report['documentURL'] ?? null,
                'violated_directive' => $report['violated-directive'] ?? $report['effectiveDirective'] ?? null,
                'blocked_uri' => $report['blocked-uri'] ?? $report['blockedURL'] ?? null,
                'disposition' => $report['disposition'] ?? null,
            ]);
        }

        return response()->noContent();
    }
}
