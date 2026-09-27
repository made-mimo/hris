<?php

use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfills the new per-day/consumption-ledger model (spec C1) against any
 * LeaveRequest that predates it — for a fresh `migrate:fresh --seed` cycle
 * this finds nothing (the seeder runs after migrations and creates its own
 * headers), so HrisDemoSeeder calls the same
 * LeaveRequestService::materializeLegacyHeader() directly after each
 * request it creates; this migration exists for the "upgrade an
 * already-seeded/live instance" path where LeaveRequest rows already exist
 * before this schema change runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(LeaveRequestService::class);

        LeaveRequest::whereDoesntHave('requestDays')->get()->each(
            fn (LeaveRequest $request) => $service->materializeLegacyHeader($request)
        );
    }

    public function down(): void
    {
        // Data backfill only — nothing to structurally reverse.
    }
};
