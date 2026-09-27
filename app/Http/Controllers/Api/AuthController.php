<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Token issuance for the REST API (spec Section 3.2/F6 — the mobile/API
 * surface). Deliberately separate from the web app's session+2FA login
 * (⚡login-form.blade.php) — a Sanctum personal-access token, not a session
 * cookie. 2FA is not yet enforced on token issuance; tracked in PLAN.md
 * alongside the rest of the API framework's known v1 gaps.
 */
class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::once($credentials)) {
            return $this->failure(401, 'Those credentials don\'t match our records.');
        }

        $user = Auth::user();
        $token = $user->createToken($request->userAgent() ?? 'api');

        return $this->success([
            'token' => $token->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], status: 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(['revoked' => true]);
    }
}
