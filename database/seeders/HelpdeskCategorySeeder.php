<?php

namespace Database\Seeders;

use App\Models\HelpdeskCategory;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Spec F4: "a seeded, confidential ticket category" for Grievance/
 * Whistleblower — every install needs this to exist, unlike ordinary
 * categories which are purely Admin-configurable. Its handler list defaults
 * to Admin + HR Admin ("HR Admin plus a named compliance contact" per
 * spec's own example), deliberately narrower than the broad HR/Admin
 * visibility every other category gets.
 */
class HelpdeskCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['IT Support', 'HR Request', 'Facilities'] as $name) {
            HelpdeskCategory::firstOrCreate(['name' => $name]);
        }

        $grievance = HelpdeskCategory::firstOrCreate(
            ['name' => 'Grievance / Whistleblower'],
            ['is_confidential' => true]
        );

        if (! $grievance->is_confidential) {
            $grievance->update(['is_confidential' => true]);
        }

        $adminRole = Role::where('slug', 'admin')->first();
        $hrAdminRole = Role::where('slug', 'hr_admin')->first();

        foreach (array_filter([$adminRole, $hrAdminRole]) as $role) {
            $grievance->handlers()->firstOrCreate(['role_id' => $role->id, 'employee_id' => null]);
        }
    }
}
