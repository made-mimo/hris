<?php

namespace Database\Factories;

use App\Models\ClaimEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaimEvent>
 */
class ClaimEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' — '.fake()->randomElement(['Business Trip', 'Site Survey', 'Client Visit']),
            'active' => true,
        ];
    }
}
