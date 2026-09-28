<?php

namespace App\Http\Controllers\Api;

use App\Models\LeaveType;
use App\Models\ModuleToggle;
use App\Models\Screen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Spec F6: "the existing 'menus' nav-tree response (unchanged in shape)
 * scoped to enabled modules and the caller's role" — the same Screen
 * registry and canView() gate that already drives the web sidebar
 * (resources/views/components/layouts/app.blade.php), reused rather than a
 * second, API-only navigation model that could drift out of sync with it.
 */
class MenuController extends ApiController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $groups = Screen::orderBy('nav_group')->orderBy('sort_order')->get()
            ->filter(fn (Screen $screen) => $user->canView($screen->key))
            ->filter(fn (Screen $screen) => ! $screen->module_key || ModuleToggle::isEnabled($screen->module_key))
            ->groupBy('nav_group')
            ->map(fn ($screens, $group) => [
                'name' => $group,
                'items' => $screens->map(fn (Screen $screen) => [
                    'key' => $screen->key,
                    'label' => $screen->label,
                    'path' => $this->pathFor($screen->key),
                    'module_key' => $screen->module_key,
                ])->values(),
            ])->values();

        return $this->success([
            'groups' => $groups,
            // Spec F6: "response metadata continues to indicate whether
            // prerequisite configuration...exists, so the client knows
            // which actions are actually usable yet." Both self-initialize
            // in this app (LeavePeriod::forYear(), a Timesheet row per
            // week) rather than ever staying genuinely unconfigured, but
            // the contract is honored here regardless.
            'ready' => [
                'leave' => LeaveType::exists(),
                'timesheets' => true,
            ],
        ]);
    }

    private function pathFor(string $screenKey): ?string
    {
        if (! RouteFacade::has($screenKey)) {
            return null;
        }

        // parse_url() returns null for a bare-domain URL with no path
        // segment at all (the "home" route at "/"), not "/" as you'd expect.
        return parse_url(route($screenKey), PHP_URL_PATH) ?? '/';
    }
}
