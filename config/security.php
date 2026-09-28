<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy mode
    |--------------------------------------------------------------------------
    |
    | PIM/HRIS alignment §3C item 4. One of:
    |   - "report-only" (default) — CSP-Report-Only header; nothing is
    |     blocked, violations are logged via /api/csp-report.
    |   - "enforce" — the real Content-Security-Policy header; the browser
    |     blocks whatever isn't allowed.
    |   - "off" — neither header is sent.
    |
    | Switch to "enforce" only after a report-only period with no
    | unexpected violations in the log.
    |
    */

    'csp_mode' => env('CSP_MODE', 'report-only'),

];
