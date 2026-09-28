<?php

namespace Database\Seeders;

use App\Models\RenewalType;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Spec 3.2's Renewal & Compliance Reminder Engine, configured per consuming
 * type (E2 Asset warranties, E3 Vehicle renewals, E5 Company Registration
 * Documents). Default reminder tiers of 60/30/14/7 days match spec E5's own
 * stated org-wide default; Admin-editable per type from there.
 */
class RenewalTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['key' => 'asset_warranty', 'label' => 'Asset Warranty/Service Contract'],
            ['key' => 'vehicle_renewal', 'label' => 'Vehicle Renewal (Insurance/License)'],
        ];

        foreach ($types as $t) {
            $type = RenewalType::updateOrCreate(['key' => $t['key']], $t);

            foreach ([60, 30, 14, 7] as $days) {
                $type->tiers()->firstOrCreate(['days_before_expiry' => $days]);
            }

            $adminRole = Role::where('slug', 'admin')->first();
            $hrAdminRole = Role::where('slug', 'hr_admin')->first();

            foreach (array_filter([$adminRole, $hrAdminRole]) as $role) {
                $type->notifyTargets()->firstOrCreate(['role_id' => $role->id, 'user_id' => null]);
            }
        }
    }
}
