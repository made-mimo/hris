<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RbacSeeder::class);
        $this->call(WorkflowSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(MasterListSeeder::class);
        $this->call(RenewalTypeSeeder::class);
        $this->call(HrisDemoSeeder::class);
    }
}
