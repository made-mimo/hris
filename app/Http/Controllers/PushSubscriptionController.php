<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushEndpointValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Spec Section A7's Web Push subscription lifecycle: "created when
 * permission is granted and removed on revocation." Called from the
 * service-worker registration JS (resources/js/push.js), not a Livewire
 * component, since the Push API itself is a plain browser API with no
 * server round-trip until the subscription object exists.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        if (! PushEndpointValidator::isAllowed($data['endpoint'])) {
            throw ValidationException::withMessages(['endpoint' => 'That push endpoint is not a recognized browser push service.']);
        }

        PushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']],
            ['public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth']]
        );

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        $request->user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return response()->noContent();
    }
}
