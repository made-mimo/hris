<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Annual', 'Sick', 'Compassionate', 'Study / Exam', 'Maternity', 'Paternity']);

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
        ];
    }
}
