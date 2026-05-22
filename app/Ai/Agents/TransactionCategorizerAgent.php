<?php

namespace App\Ai\Agents;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class TransactionCategorizerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You are a financial transaction categorization assistant.

        Your task is to analyze each transaction's name and description, then assign the most appropriate category from the provided list.

        For each transaction, return its ID and the matched category ID. If no category is a suitable match, return null for category_id.

        Only use category IDs from the provided list — never invent new ones.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array()
                ->items(
                    $schema->object([
                        'id' => $schema->integer()
                            ->description('The transaction ID.')
                            ->required(),
                        'category_id' => $schema->integer()
                            ->description('The matched category ID. Null if no suitable category exists.'),
                    ])
                )
                ->description('Transactions with their assigned category IDs.')
                ->required(),
        ];
    }

    /**
     * Categorize an array of transactions using the provided categories.
     *
     * @param  array<int, array<string, mixed>>  $transactions
     * @return array<int, array<string, mixed>>
     */
    public function categorize(array $transactions, Collection $categories): array
    {
        $categoriesContext = $categories->map(fn (Category $category) => [
            'id' => $category->id,
            'name' => $category->name,
        ])->values()->toArray();

        $transactionsContext = array_map(fn (array $transaction) => [
            'id' => $transaction['id'],
            'name' => $transaction['name'],
            'description' => $transaction['description'] ?? null,
        ], $transactions);

        $prompt = sprintf(
            "Categorize the following transactions using the available categories.\n\nAvailable categories:\n%s\n\nTransactions to categorize:\n%s",
            json_encode($categoriesContext, JSON_PRETTY_PRINT),
            json_encode($transactionsContext, JSON_PRETTY_PRINT),
        );

        $response = $this->prompt($prompt, provider: Lab::Gemini);

        $categoryMap = collect($response['transactions'])->keyBy('id');

        return array_map(function (array $transaction) use ($categoryMap): array {
            $transaction['category_id'] = $categoryMap->has($transaction['id'])
                ? ($categoryMap->get($transaction['id'])['category_id'] ?? null)
                : ($transaction['category_id'] ?? null);

            return $transaction;
        }, $transactions);
    }
}
