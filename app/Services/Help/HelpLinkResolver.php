<?php

namespace App\Services\Help;

use App\Models\Screen;
use Illuminate\Http\Request;

/**
 * Spec F5: "per-screen contextual help via a label/tag mapped to help-center
 * search." The current screen is read off the route's own `screen:<key>`
 * middleware (the same tag every route already declares for access control,
 * see EnsureScreenAccess) rather than the route name, since route names
 * don't always match a screen key one-to-one (e.g. `employees.show`).
 */
class HelpLinkResolver
{
    public function __construct(private HelpProviderInterface $provider) {}

    /** Null when the provider isn't configured (link should be hidden) or the current route has no screen middleware. */
    public function currentScreenHelpUrl(Request $request): ?string
    {
        if (! $this->provider->isConfigured()) {
            return null;
        }

        $tag = ($key = $this->currentScreenKey($request)) ? Screen::where('key', $key)->value('help_tag') : null;

        return $this->provider->urlFor($tag);
    }

    public function currentScreenKey(Request $request): ?string
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'screen:')) {
                return substr($middleware, strlen('screen:'));
            }
        }

        return null;
    }
}
