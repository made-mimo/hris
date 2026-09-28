<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/** Spec F6: "vehicle...self-view" — mirrors AssetController's self-only scope exactly (spec E3: "same for vehicles"). */
class VehicleController extends ApiController
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'This account has no employee record.');

        $vehicles = Vehicle::with('renewals')
            ->where('current_employee_id', $employee->id)
            ->get();

        return $this->success(VehicleResource::collection($vehicles));
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $vehicle->current_employee_id === $employee->id, 403, 'You do not have access to this vehicle.');

        return $this->success(new VehicleResource($vehicle->load('renewals')));
    }
}
