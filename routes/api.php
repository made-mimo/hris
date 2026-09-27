<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaveRequestController;
use Illuminate\Support\Facades\Route;

/**
 * Spec Section 3.2's REST API framework — see App\Http\Controllers\Api\ApiController
 * and App\Support\ApiEnvelope for the shared conventions (envelope shape,
 * error format) every endpoint here follows. Leave Management is the
 * representative slice this Phase 0 service is built and proven against;
 * every other module's API controller should follow the same shape rather
 * than inventing its own, per spec's "designed from the outset for full
 * per-module coverage... rather than retrofitted later."
 */
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
    Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update']);
    Route::delete('/leave-requests', [LeaveRequestController::class, 'destroy']);
});
