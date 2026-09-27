<?php

namespace Database\Factories;

use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimLine;
use App\Models\ExpenseType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseClaimLine>
 */
class ExpenseClaimLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_claim_id' => ExpenseClaim::factory(),
            'expense_type_id' => ExpenseType::factory(),
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'note' => fake()->sentence(4),
            'amount' => fake()->numberBetween(5000, 200000),
        ];
    }
}
