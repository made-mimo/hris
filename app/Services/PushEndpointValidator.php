<?php

namespace App\Services;

/**
 * PIM/HRIS alignment §3C item 8 — the server later POSTs to whatever
 * endpoint a subscription stores (see NotificationService::sendPush()), so
 * accepting any https URL at all would let a signed-in user aim that
 * server-side request at an internal address (SSRF). Restricts it to the
 * known browser push services. Checked both when a subscription is saved
 * and again right before it's used, in case the allowlist itself ever
 * narrows after a subscription was already stored.
 */
class PushEndpointValidator
{
    private const EXACT_HOSTS = [
        'fcm.googleapis.com',
        'android.googleapis.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
    ];

    private const SUFFIX_HOSTS = [
        '.push.services.mozilla.com',
        '.notify.windows.com',
        '.push.apple.com',
    ];

    public static function isAllowed(string $endpoint): bool
    {
        $url = parse_url($endpoint);
        $host = strtolower($url['host'] ?? '');

        if (($url['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }

        if (in_array($host, self::EXACT_HOSTS, true)) {
            return true;
        }

        foreach (self::SUFFIX_HOSTS as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
