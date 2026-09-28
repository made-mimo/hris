<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\ExpenseClaimController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PolicyDocumentController;
use App\Http\Controllers\Api\PulseSurveyController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

/**
 * Spec Section 3.2's REST API framework — see App\Http\Controllers\Api\ApiController
 * and App\Support\ApiEnvelope for the shared conventions (envelope shape,
 * error format) every endpoint here follows. Leave Management is the
 * representative slice this Phase 0 service is built and proven against;
 * every other module's API controller below follows the same shape rather
 * than inventing its own, per spec's "designed from the outset for full
 * per-module coverage... rather than retrofitted later."
 *
 * Spec F6 scopes this surface to the self-service actions spec 3.4/5
 * (Non-Functional Requirements) name as the mobile baseline — "Apply for
 * Leave, Submit a Claim, Punch In/Out, Raise a Ticket for ESS; approval
 * queues for Supervisor/HR" plus directory/notifications/policy-ack/pulse-
 * survey/asset-vehicle-self-view — rather than a full parity rebuild of
 * every admin/management screen in Domains B-F as a second frontend; see
 * PLAN.md for that scope decision written out in full.
 */
Route::post('/login', [AuthController::class, 'login']);

// A `2fa-pending` token (see AuthController::login()'s doc comment) can
// reach this one endpoint and nothing else.
Route::middleware(['auth:sanctum', 'abilities:2fa:verify'])->group(function () {
    Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
});

// Logout revokes whatever token was sent, including a still-pending one a
// client wants to abandon — deliberately not gated by ability.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'abilities:*'])->group(function () {
    Route::get('/menus', [MenuController::class, 'index']);

    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
    Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update']);
    Route::delete('/leave-requests', [LeaveRequestController::class, 'destroy']);
    Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);

    Route::get('/expense-claims', [ExpenseClaimController::class, 'index']);
    Route::post('/expense-claims', [ExpenseClaimController::class, 'store']);
    Route::get('/expense-claims/{expenseClaim}', [ExpenseClaimController::class, 'show']);
    Route::post('/expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve']);
    Route::post('/expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject']);

    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/current', [AttendanceController::class, 'current']);
    Route::post('/attendance/punch-in', [AttendanceController::class, 'punchIn']);
    Route::post('/attendance/punch-out', [AttendanceController::class, 'punchOut']);

    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{asset}', [AssetController::class, 'show']);

    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);

    Route::get('/policy-documents', [PolicyDocumentController::class, 'index']);
    Route::post('/policy-documents/{policyDocument}/acknowledge', [PolicyDocumentController::class, 'acknowledge']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'addComment']);
    Route::post('/tickets/{ticket}/status', [TicketController::class, 'updateStatus']);

    Route::get('/directory', [DirectoryController::class, 'index']);
    Route::get('/directory/{employee}', [DirectoryController::class, 'show']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/clear', [NotificationController::class, 'clear']);
    Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll']);

    Route::get('/pulse-surveys', [PulseSurveyController::class, 'index']);
    Route::post('/pulse-surveys/{pulseSurveyRun}/respond', [PulseSurveyController::class, 'respond']);
});
