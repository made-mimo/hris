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
        $this->call(HelpdeskCategorySeeder::class);

        // Security fix: this seeder creates known-password demo accounts
        // (including an Admin and an HR Admin) — fine for this dev box and
        // CI (both run as APP_ENV=local), but `php artisan db:seed` must
        // never be able to silently plant those same logins on a real
        // deployment.
        if (app()->environment(['local', 'testing'])) {
            $this->call(HrisDemoSeeder::class);
        } else {
            $this->command?->warn('Skipping HrisDemoSeeder: not running in a local/testing environment.');
        }
    }
}
