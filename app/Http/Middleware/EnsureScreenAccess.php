<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Screen/page enforcement (spec Section 3.2, tier 1 of 3), driven entirely
 * by the Role Permission Matrix via PermissionService — replaces the old
 * hard-coded EnsureHrRole check. Usage: ->middleware('screen:settings').
 */
class EnsureScreenAccess
{
    public function __construct(protected PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $screenKey): Response
    {
        if ($this->permissions->canViewScreen($request->user(), $screenKey)) {
            return $next($request);
        }

        abort(403, "Your role doesn't have access to this screen.");
    }
}
