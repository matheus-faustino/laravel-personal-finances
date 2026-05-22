<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'date' => fake()->date(),
            'value' => fake()->randomFloat(2, 1, 10000),
            'category_id' => Category::factory(),
            'user_id' => User::factory()->client(),
            'document_id' => null,
        ];
    }

    public function uncategorized(): static
    {
        return $this->state(['category_id' => null]);
    }
}
