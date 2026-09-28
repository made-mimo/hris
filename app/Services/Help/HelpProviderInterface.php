<?php

namespace App\Services\Help;

/**
 * Spec F5: "a pluggable help-provider interface...so the destination can be
 * swapped or replaced with an in-app article system without touching
 * calling code." Callers (the topbar Help link, and any future API
 * consumer) depend only on this contract, never on a concrete provider.
 */
interface HelpProviderInterface
{
    /** Spec F5: "URL validation before the help link is shown at all." False hides the Help link entirely. */
    public function isConfigured(): bool;

    /** Spec F5: "graceful fallback to a default help-center URL" — a null/blank tag (or one not mapped to anything specific) returns the provider's default landing page rather than a broken search. */
    public function urlFor(?string $tag): string;
}
