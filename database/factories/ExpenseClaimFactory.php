<?php

namespace Database\Factories;

use App\Models\ClaimEvent;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseClaim>
 */
class ExpenseClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'CLM-'.now()->format('Ymd').'-'.fake()->unique()->numerify('###'),
            'employee_id' => Employee::factory(),
            'claim_event_id' => ClaimEvent::factory(),
            'currency' => 'NGN',
            'status' => 'draft',
        ];
    }
}
