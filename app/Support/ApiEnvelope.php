<?php

namespace App\Support;

/**
 * Spec Section 3.2's REST API framework: "consistent error envelopes (400
 * validation, 403 authorization, 404 not found)" and a consistent success
 * shape across every endpoint. One place both the global exception handler
 * (bootstrap/app.php) and every Api\*Controller build responses from, so no
 * endpoint can drift from the shape by hand-rolling its own JSON.
 */
class ApiEnvelope
{
    public static function success(mixed $data, array $meta = []): array
    {
        // `data` must always be present, even when it is legitimately null
        // (e.g. "no attendance record currently open") — array_filter()
        // over the whole array would silently drop a null `data` entry too,
        // collapsing the envelope to {} and making "nothing here" and "an
        // explicit null" indistinguishable to a client.
        $envelope = ['data' => $data];

        if ($meta) {
            $envelope['meta'] = $meta;
        }

        return $envelope;
    }

    public static function error(int $code, string $message, array $details = []): array
    {
        return [
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ], fn ($v) => ! is_null($v)),
        ];
    }
}
