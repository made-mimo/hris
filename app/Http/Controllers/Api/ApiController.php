<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;

/**
 * Spec Section 3.2's REST API framework base: every Api\*Controller returns
 * through success()/failure() so the response envelope can never drift by
 * hand-rolling JSON per endpoint. See App\Support\ApiEnvelope for the shape,
 * and bootstrap/app.php for the matching exception-driven error envelopes
 * (validation/authorization/not-found) this deliberately doesn't duplicate.
 */
abstract class ApiController extends Controller
{
    protected function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(ApiEnvelope::success($data, $meta), $status);
    }

    protected function failure(int $code, string $message, array $details = []): JsonResponse
    {
        return response()->json(ApiEnvelope::error($code, $message, $details), $code);
    }
}
