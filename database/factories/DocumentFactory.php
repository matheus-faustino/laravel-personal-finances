<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
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
            'file' => 'documents/0/'.fake()->uuid().'.pdf',
            'user_id' => User::factory()->user(),
            'status' => DocumentStatus::Uploaded,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Document $document): void {
            if (str_starts_with($document->file, 'documents/0/')) {
                $document->update([
                    'file' => "documents/{$document->user_id}/".fake()->uuid().'.pdf',
                ]);
            }
        });
    }
}
