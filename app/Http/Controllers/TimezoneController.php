<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Records the browser's own declared IANA timezone (via the client-side Intl
 * API — see resources/js/app.js) so timestamps render in the viewer's local
 * time. Deliberately not IP-address or GPS geolocation — spec Section C3
 * explicitly excludes location capture for this purpose ("that would be new
 * scope, not a port"); this only reads a value the browser already knows
 * about itself, with no location permission prompt and no coordinates.
 */
class TimezoneController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
        ]);

        $request->session()->put('client_timezone', $data['timezone']);

        if ($request->user() && $request->user()->timezone !== $data['timezone']) {
            $request->user()->update(['timezone' => $data['timezone']]);
        }

        return response()->noContent();
    }
}
