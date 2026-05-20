<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'file' => 'documents/'.fake()->numberBetween(1, 999).'/'.fake()->uuid().'.pdf',
            'user_id' => User::factory()->client(),
        ];
    }
}
