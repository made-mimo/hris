<?php

namespace App\Services\Help;

use App\Models\Setting;

/**
 * Spec F5: "the reference system ships an implementation for a common
 * third-party help-desk/knowledge-base platform." Modeled on Zendesk Help
 * Center's public URL scheme — a search-results page keyed by a free-text
 * query string — since that needs only an Admin-configured base URL, no API
 * key or authenticated call, to resolve a useful destination.
 */
class ZendeskHelpProvider implements HelpProviderInterface
{
    public function isConfigured(): bool
    {
        return $this->validBaseUrl() !== null;
    }

    public function urlFor(?string $tag): string
    {
        $base = $this->validBaseUrl() ?? '';

        if (! $tag) {
            return $base.'/hc/en-us';
        }

        return $base.'/hc/en-us/search?query='.urlencode($tag);
    }

    private function validBaseUrl(): ?string
    {
        $url = Setting::current()->help_provider_base_url;

        if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return rtrim($url, '/');
    }
}
