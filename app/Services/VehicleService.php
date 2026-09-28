<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MasterListItem;
use App\Models\SubUnit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFuelLog;
use App\Models\VehicleRenewal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec E3: current assignment is to EITHER an employee OR a sub-unit, never
 * both — enforced here, not at the DB level. Driving-license validation at
 * assignment is a warning-with-override, not a hard block: the caller must
 * pass an explicit override reason once warned, which is logged on the
 * resulting assignment-history row.
 */
class VehicleService
{
    public function __construct(private RenewalReminderEngine $renewals) {}

    /** @return array{valid: bool, reason: ?string} */
    public function checkDrivingLicense(Employee $employee): array
    {
        $typeId = MasterListItem::drivingLicenseTypeId();

        if (! $typeId) {
            return ['valid' => true, 'reason' => null];
        }

        $license = $employee->licenses()->where('license_type_id', $typeId)->latest('expiry_date')->first();

        if (! $license) {
            return ['valid' => false, 'reason' => 'No driving license on file for this employee.'];
        }

        if ($license->expiry_date && $license->expiry_date->isPast()) {
            return ['valid' => false, 'reason' => 'This employee\'s driving license expired on '.$license->expiry_date->format('j M Y').'.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function assign(Vehicle $vehicle, ?Employee $employee, ?SubUnit $subUnit, ?string $overrideReason, ?User $overriddenBy): void
    {
        abort_if($vehicle->status === 'retired', 422, 'A retired vehicle cannot be assigned.');
        abort_if((bool) $employee === (bool) $subUnit, 422, 'Assign to exactly one of an employee or a department, not both.');

        DB::transaction(function () use ($vehicle, $employee, $subUnit, $overrideReason, $overriddenBy) {
            $vehicle->assignmentHistory()->whereNull('ended_at')->update(['ended_at' => now()]);

            $vehicle->assignmentHistory()->create([
                'employee_id' => $employee?->id,
                'sub_unit_id' => $subUnit?->id,
                'started_at' => now(),
                'override_reason' => $overrideReason,
                'overridden_by_id' => $overriddenBy?->id,
            ]);

            $vehicle->update([
                'current_employee_id' => $employee?->id,
                'current_sub_unit_id' => $subUnit?->id,
                'assigned_at' => now(),
            ]);
        });
    }

    public function unassign(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            $vehicle->assignmentHistory()->whereNull('ended_at')->update(['ended_at' => now()]);

            $vehicle->update(['current_employee_id' => null, 'current_sub_unit_id' => null, 'assigned_at' => null]);
        });
    }

    public function setStatus(Vehicle $vehicle, string $status): void
    {
        abort_unless(in_array($status, Vehicle::STATUSES, true), 422, 'Invalid status.');

        if ($status === 'retired') {
            $this->unassign($vehicle);
        }

        $vehicle->update(['status' => $status]);
    }

    public function logFuel(Vehicle $vehicle, Carbon $date, int $odometerReading, ?float $fuelCost, ?string $notes): VehicleFuelLog
    {
        return VehicleFuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => $date,
            'odometer_reading' => $odometerReading,
            'fuel_cost' => $fuelCost,
            'notes' => $notes,
        ]);
    }

    public function addRenewal(Vehicle $vehicle, string $label, ?string $provider, ?string $referenceNumber, Carbon $issueDate, Carbon $expiryDate, ?string $notes, ?int $mileageInterval): VehicleRenewal
    {
        if ($expiryDate->lte($issueDate)) {
            throw ValidationException::withMessages(['expiryDate' => 'The expiry date must be after the issue date.']);
        }

        return DB::transaction(function () use ($vehicle, $label, $provider, $referenceNumber, $issueDate, $expiryDate, $notes, $mileageInterval) {
            // Spec E3: "re-renewing creates a new row" — retire the prior
            // renewal of the same label so it stops nagging, rather than
            // editing it in place.
            $vehicle->renewals()->where('label', $label)
                ->each(function (VehicleRenewal $prior) {
                    if ($prior->renewable && $prior->renewable->status !== 'retired') {
                        $this->renewals->retire($prior->renewable);
                    }
                });

            $dueAtMileage = $mileageInterval ? ($vehicle->latestOdometerReading() ?? 0) + $mileageInterval : null;

            $renewal = VehicleRenewal::create([
                'vehicle_id' => $vehicle->id,
                'label' => $label,
                'provider' => $provider,
                'reference_number' => $referenceNumber,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'notes' => $notes,
                'mileage_interval' => $mileageInterval,
                'due_at_mileage' => $dueAtMileage,
            ]);

            $this->renewals->register($renewal, 'vehicle_renewal', $expiryDate);

            return $renewal;
        });
    }

    public function deleteVehicle(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            foreach ($vehicle->renewals as $renewal) {
                $renewal->renewable?->delete();
            }

            $vehicle->customFieldValues()->delete();
            $vehicle->assignmentHistory()->delete();
            $vehicle->delete();
        });
    }
}
