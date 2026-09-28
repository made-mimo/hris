<?php

namespace Database\Seeders;

use App\Models\MasterListItem;
use Illuminate\Database\Seeder;

/**
 * Master list items (Job Categories, License Types, etc. — spec B1) are
 * otherwise purely Admin-configurable with nothing pre-seeded. "Driving
 * License" is the one exception: spec E3's vehicle-assignment validation
 * needs a canonical license_type row to check an employee's B2 Qualifications
 * against, so it must exist in every install rather than depend on an Admin
 * happening to create one with this exact name.
 */
class MasterListSeeder extends Seeder
{
    public function run(): void
    {
        MasterListItem::firstOrCreate([
            'type' => MasterListItem::TYPE_LICENSE_TYPE,
            'name' => 'Driving License',
        ]);
    }
}
