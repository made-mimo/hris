<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMaintenanceLog;
use App\Models\AssetWarranty;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec E2: "status/assignee consistency [is] enforced server-side: status
 * cannot be 'Assigned' without an assignee, and assigning an asset is a
 * dedicated action, never an incidental side effect of an unrelated field
 * update." Assignment history is gapless — any reassignment closes the
 * prior open period and opens a new one, in one transaction.
 */
class AssetService
{
    public function __construct(private RenewalReminderEngine $renewals) {}

    public function assign(Asset $asset, Employee $employee): void
    {
        abort_if($asset->status === 'retired', 422, 'A retired asset cannot be assigned.');

        DB::transaction(function () use ($asset, $employee) {
            $asset->assignmentHistory()->whereNull('ended_at')->update(['ended_at' => now()]);

            $asset->assignmentHistory()->create([
                'employee_id' => $employee->id,
                'started_at' => now(),
            ]);

            $asset->update([
                'status' => 'assigned',
                'current_employee_id' => $employee->id,
                'assigned_at' => now(),
            ]);
        });
    }

    public function unassign(Asset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $asset->assignmentHistory()->whereNull('ended_at')->update(['ended_at' => now()]);

            $asset->update([
                'status' => 'available',
                'current_employee_id' => null,
                'assigned_at' => null,
            ]);
        });
    }

    /** Spec E2: "transitioning an asset to 'In Repair' (or logging a repair against any status) creates a dated entry." */
    public function logMaintenance(Asset $asset, Carbon $date, string $description, ?float $cost, ?string $vendor, bool $setInRepair): AssetMaintenanceLog
    {
        return DB::transaction(function () use ($asset, $date, $description, $cost, $vendor, $setInRepair) {
            $log = AssetMaintenanceLog::create([
                'asset_id' => $asset->id,
                'log_date' => $date,
                'description' => $description,
                'cost' => $cost,
                'vendor' => $vendor,
            ]);

            if ($setInRepair) {
                $asset->update(['status' => 'in_repair']);
            }

            return $log;
        });
    }

    public function retire(Asset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $asset->assignmentHistory()->whereNull('ended_at')->update(['ended_at' => now()]);
            $asset->update(['status' => 'retired', 'current_employee_id' => null, 'assigned_at' => null]);
        });
    }

    public function addWarranty(Asset $asset, string $provider, ?string $contractNumber, Carbon $issueDate, Carbon $expiryDate, ?string $notes): AssetWarranty
    {
        if ($expiryDate->lte($issueDate)) {
            throw ValidationException::withMessages(['expiryDate' => 'The expiry date must be after the issue date.']);
        }

        return DB::transaction(function () use ($asset, $provider, $contractNumber, $issueDate, $expiryDate, $notes) {
            $warranty = AssetWarranty::create([
                'asset_id' => $asset->id,
                'provider' => $provider,
                'contract_number' => $contractNumber,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'notes' => $notes,
            ]);

            $this->renewals->register($warranty, 'asset_warranty', $expiryDate);

            return $warranty;
        });
    }

    public function renewWarranty(AssetWarranty $warranty, Carbon $newExpiryDate): void
    {
        $warranty->update(['expiry_date' => $newExpiryDate]);

        if ($warranty->renewable) {
            $this->renewals->renew($warranty->renewable, $newExpiryDate);
        }
    }
}
