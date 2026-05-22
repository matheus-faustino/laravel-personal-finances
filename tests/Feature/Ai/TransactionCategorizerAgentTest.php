<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\TransactionCategorizerAgent;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCategorizerAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_categorizes_transactions_by_name_and_description(): void
    {
        $food = Category::factory()->create(['name' => 'Food']);
        $transport = Category::factory()->create(['name' => 'Transport']);

        TransactionCategorizerAgent::fake([
            [
                'transactions' => [
                    ['id' => 1, 'category_id' => $food->id],
                    ['id' => 2, 'category_id' => $transport->id],
                ],
            ],
        ]);

        $transactions = [
            ['id' => 1, 'name' => 'Lunch at Restaurant', 'description' => 'Pizza and drinks', 'value' => 50.00, 'date' => '2024-01-15', 'category_id' => null],
            ['id' => 2, 'name' => 'Uber ride', 'description' => 'Trip to airport', 'value' => 35.00, 'date' => '2024-01-15', 'category_id' => null],
        ];

        $result = (new TransactionCategorizerAgent)->categorize($transactions, Category::all());

        $this->assertCount(2, $result);
        $this->assertEquals($food->id, $result[0]['category_id']);
        $this->assertEquals($transport->id, $result[1]['category_id']);
    }

    public function test_it_returns_null_category_id_when_no_match_is_found(): void
    {
        Category::factory()->create(['name' => 'Travel']);

        TransactionCategorizerAgent::fake([
            [
                'transactions' => [
                    ['id' => 1, 'category_id' => null],
                ],
            ],
        ]);

        $transactions = [
            ['id' => 1, 'name' => 'Unknown expense', 'description' => null, 'value' => 10.00, 'date' => '2024-01-15', 'category_id' => null],
        ];

        $result = (new TransactionCategorizerAgent)->categorize($transactions, Category::all());

        $this->assertNull($result[0]['category_id']);
    }

    public function test_it_returns_same_transaction_structure_with_category_id_filled(): void
    {
        $category = Category::factory()->create(['name' => 'Shopping']);

        TransactionCategorizerAgent::fake([
            [
                'transactions' => [
                    ['id' => 42, 'category_id' => $category->id],
                ],
            ],
        ]);

        $transactions = [
            ['id' => 42, 'name' => 'Amazon purchase', 'description' => 'Books and gadgets', 'value' => 250.00, 'date' => '2024-03-10', 'category_id' => null],
        ];

        $result = (new TransactionCategorizerAgent)->categorize($transactions, Category::all());

        $this->assertCount(1, $result);
        $this->assertEquals(42, $result[0]['id']);
        $this->assertEquals('Amazon purchase', $result[0]['name']);
        $this->assertEquals('Books and gadgets', $result[0]['description']);
        $this->assertEquals(250.00, $result[0]['value']);
        $this->assertEquals('2024-03-10', $result[0]['date']);
        $this->assertEquals($category->id, $result[0]['category_id']);
    }

    public function test_it_prompts_with_transactions_and_categories(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);

        TransactionCategorizerAgent::fake([
            [
                'transactions' => [
                    ['id' => 1, 'category_id' => $category->id],
                ],
            ],
        ]);

        $transactions = [
            ['id' => 1, 'name' => 'Supermarket', 'description' => 'Groceries', 'value' => 100.00, 'date' => '2024-01-01', 'category_id' => null],
        ];

        (new TransactionCategorizerAgent)->categorize($transactions, Category::all());

        $expectedPrompt = sprintf(
            "Categorize the following transactions using the available categories.\n\nAvailable categories:\n%s\n\nTransactions to categorize:\n%s",
            json_encode([['id' => $category->id, 'name' => 'Food']], JSON_PRETTY_PRINT),
            json_encode([['id' => 1, 'name' => 'Supermarket', 'description' => 'Groceries']], JSON_PRETTY_PRINT),
        );

        TransactionCategorizerAgent::assertPrompted($expectedPrompt);
    }
}
