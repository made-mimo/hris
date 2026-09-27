<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\SubUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'employee_id' => 'SIL'.fake()->unique()->numerify('####0##'),
            'first_name' => $first,
            'last_name' => $last,
            'initials' => mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1)),
            'job_title_id' => JobTitle::firstOrCreate(['name' => fake()->jobTitle()])->id,
            'sub_unit_id' => SubUnit::firstOrCreate(['name' => fake()->randomElement(['AV Integration', 'Service & Support', 'Projects', 'Sales', 'Finance'])])->id,
            'location_id' => Location::firstOrCreate(['name' => 'Lagos'])->id,
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
        ];
    }
}
