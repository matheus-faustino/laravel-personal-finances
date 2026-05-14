<?php

namespace Tests\Unit\Transaction;

use App\Interfaces\TransactionServiceInterface;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(TransactionServiceInterface::class);
    }

    public function test_index_returns_all_transactions_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Transaction::factory()->count(2)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(3)->create(['user_id' => $clientB->id]);

        $result = $this->service->index($admin);

        $this->assertCount(5, $result);
    }

    public function test_index_returns_only_own_transactions_for_client(): void
    {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        Transaction::factory()->count(3)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->index($clientA);

        $this->assertCount(3, $result);
    }

    public function test_index_returns_empty_collection_when_no_transactions_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $result = $this->service->index($admin);

        $this->assertCount(0, $result);
    }

    public function test_show_returns_the_given_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $result = $this->service->show($transaction);

        $this->assertTrue($result->is($transaction));
    }

    public function test_store_forces_user_id_to_authenticated_user(): void
    {
        $client = User::factory()->client()->create();
        $category = Category::factory()->create();

        $data = [
            'name' => 'Client Invoice',
            'date' => '2026-01-15',
            'value' => 200.00,
            'category_id' => $category->id,
        ];

        $result = $this->service->store($client, $data);

        $this->assertSame($client->id, $result->user_id);
        $this->assertDatabaseHas('transactions', ['name' => 'Client Invoice', 'user_id' => $client->id]);
    }

    public function test_store_overrides_spoofed_user_id_for_client(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $category = Category::factory()->create();

        $data = [
            'name' => 'Spoofed Invoice',
            'date' => '2026-01-15',
            'value' => 99.00,
            'category_id' => $category->id,
            'user_id' => $other->id,
        ];

        $result = $this->service->store($client, $data);

        $this->assertSame($client->id, $result->user_id);
        $this->assertDatabaseMissing('transactions', ['name' => 'Spoofed Invoice', 'user_id' => $other->id]);
    }

    public function test_update_changes_transaction_fields(): void
    {
        $transaction = Transaction::factory()->create(['name' => 'Old Name', 'value' => 100.00]);
        $category = Category::factory()->create();

        $this->service->update($transaction, [
            'name' => 'New Name',
            'date' => '2026-03-01',
            'value' => 250.00,
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'name' => 'New Name']);
    }

    public function test_update_returns_the_updated_transaction(): void
    {
        $transaction = Transaction::factory()->create();
        $category = Category::factory()->create();

        $result = $this->service->update($transaction, [
            'name' => 'Updated',
            'date' => '2026-03-01',
            'value' => 10.00,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($result->is($transaction));
    }

    public function test_destroy_deletes_the_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $this->service->destroy($transaction);

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }
}
