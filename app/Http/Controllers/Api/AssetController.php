<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AssetResource;
use App\Models\Asset;
use Illuminate\Http\Request;

/**
 * Spec F6: "asset...self-view" — deliberately narrower than the web
 * Assets screen (which grants Admin/HR Admin/HR Officer a full register):
 * this endpoint only ever returns the caller's own currently-assigned
 * assets, matching spec E2's "an ordinary ESS user sees only assets
 * currently assigned to them" as the mobile API's entire surface here,
 * with no manager-scope parameter to widen it.
 */
class AssetController extends ApiController
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $assets = Asset::with('category', 'warranties')
            ->where('current_employee_id', $employee->id)
            ->get();

        return $this->success(AssetResource::collection($assets));
    }

    public function show(Request $request, Asset $asset)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $asset->current_employee_id === $employee->id, 403, 'You do not have access to this asset.');

        return $this->success(new AssetResource($asset->load('category', 'warranties')));
    }
}
