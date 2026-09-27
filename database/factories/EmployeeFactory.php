<?php

namespace Database\Factories;

use App\Models\Employee;
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
            'job_title' => fake()->jobTitle(),
            'department' => fake()->randomElement(['AV Integration', 'Service & Support', 'Projects', 'Sales', 'Finance']),
            'location' => 'Lagos',
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
        ];
    }
}
