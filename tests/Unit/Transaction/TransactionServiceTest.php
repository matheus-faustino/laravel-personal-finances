<?php

namespace Tests\Unit\Transaction;

use App\Interfaces\TransactionServiceInterface;
use App\Models\Category;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_get_all_for_user_returns_all_transactions_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Transaction::factory()->count(2)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(3)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($admin);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(5, $result);
    }

    public function test_get_all_for_user_returns_only_own_transactions_for_client(): void
    {
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Transaction::factory()->count(3)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($clientA);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_get_all_for_user_returns_empty_collection_when_no_transactions_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $result = $this->service->getAllForUser($admin);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_get_all_for_user_filters_by_start_date(): void
    {
        $admin = User::factory()->admin()->create();

        Transaction::factory()->create(['date' => '2026-01-15']);
        Transaction::factory()->create(['date' => '2026-03-10']);

        $result = $this->service->getAllForUser($admin, ['start_date' => '2026-02-01']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_all_for_user_filters_by_end_date(): void
    {
        $admin = User::factory()->admin()->create();

        Transaction::factory()->create(['date' => '2026-01-15']);
        Transaction::factory()->create(['date' => '2026-03-10']);

        $result = $this->service->getAllForUser($admin, ['end_date' => '2026-02-28']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_all_for_user_filters_by_date_range(): void
    {
        $admin = User::factory()->admin()->create();

        Transaction::factory()->create(['date' => '2025-12-01']);
        Transaction::factory()->create(['date' => '2026-01-15']);
        Transaction::factory()->create(['date' => '2026-03-10']);

        $result = $this->service->getAllForUser($admin, ['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_all_for_user_filters_by_user_id_when_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Transaction::factory()->count(3)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($admin, ['user_id' => $clientA->id]);

        $this->assertSame(3, $result->total());
    }

    public function test_get_all_for_user_ignores_user_id_filter_for_non_admin(): void
    {
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Transaction::factory()->count(3)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(2)->create(['user_id' => $clientB->id]);

        $result = $this->service->getAllForUser($clientA, ['user_id' => $clientB->id]);

        $this->assertSame(3, $result->total());
    }

    public function test_get_all_for_user_filters_by_category_id(): void
    {
        $admin = User::factory()->admin()->create();
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();

        Transaction::factory()->count(3)->create(['category_id' => $catA->id]);
        Transaction::factory()->count(2)->create(['category_id' => $catB->id]);

        $result = $this->service->getAllForUser($admin, ['category_id' => $catA->id]);

        $this->assertSame(3, $result->total());
        foreach ($result->items() as $item) {
            $this->assertSame($catA->id, $item->category_id);
        }
    }

    public function test_get_all_for_user_paginates_results(): void
    {
        $client = User::factory()->user()->create();
        Transaction::factory()->count(20)->create(['user_id' => $client->id]);

        $result = $this->service->getAllForUser($client, ['per_page' => 5]);

        $this->assertCount(5, $result);
        $this->assertSame(20, $result->total());
    }

    public function test_get_all_for_user_uses_default_per_page_of_15(): void
    {
        $admin = User::factory()->admin()->create();
        Transaction::factory()->count(20)->create();

        $result = $this->service->getAllForUser($admin);

        $this->assertCount(15, $result);
        $this->assertSame(20, $result->total());
    }

    public function test_get_all_for_document_returns_only_transactions_for_that_document(): void
    {
        $document = Document::factory()->create();
        $otherDocument = Document::factory()->create();

        Transaction::factory()->count(3)->create(['document_id' => $document->id]);
        Transaction::factory()->count(2)->create(['document_id' => $otherDocument->id]);

        $result = $this->service->getAllForDocument($document);

        $this->assertCount(3, $result);
        $result->each(fn ($t) => $this->assertSame($document->id, $t->document_id));
    }

    public function test_get_all_for_document_returns_empty_collection_when_no_transactions_linked(): void
    {
        $document = Document::factory()->create();

        $result = $this->service->getAllForDocument($document);

        $this->assertCount(0, $result);
    }

    public function test_get_returns_transaction_by_id(): void
    {
        $transaction = Transaction::factory()->create();

        $result = $this->service->get($transaction->id);

        $this->assertTrue($result->is($transaction));
    }

    public function test_get_throws_exception_for_non_existent_transaction(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->get(999999);
    }

    public function test_create_forces_user_id_to_authenticated_user(): void
    {
        $client = User::factory()->user()->create();
        $category = Category::factory()->create();

        $data = [
            'name' => 'Client Invoice',
            'date' => '2026-01-15',
            'value' => 200.00,
            'category_id' => $category->id,
        ];

        $result = $this->service->create($client, $data);

        $this->assertSame($client->id, $result->user_id);
        $this->assertDatabaseHas('transactions', ['name' => 'Client Invoice', 'user_id' => $client->id]);
    }

    public function test_create_overrides_spoofed_user_id_for_client(): void
    {
        $client = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $category = Category::factory()->create();

        $data = [
            'name' => 'Spoofed Invoice',
            'date' => '2026-01-15',
            'value' => 99.00,
            'category_id' => $category->id,
            'user_id' => $other->id,
        ];

        $result = $this->service->create($client, $data);

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

    public function test_delete_deletes_the_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $this->service->delete($transaction);

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_bulk_update_updates_multiple_transactions(): void
    {
        $client = User::factory()->user()->create();
        $category = Category::factory()->create();
        $transactions = Transaction::factory()->count(3)->create(['user_id' => $client->id]);

        $updateData = $transactions->map(fn ($t) => [
            'id' => $t->id,
            'name' => 'Bulk Updated '.$t->id,
            'description' => 'Updated desc',
            'date' => '2026-06-01',
            'value' => 999.99,
            'category_id' => $category->id,
        ])->toArray();

        $result = $this->service->bulkUpdate($client, $updateData);

        $this->assertCount(3, $result);
        foreach ($result as $index => $transaction) {
            $this->assertSame('Bulk Updated '.$transactions[$index]->id, $transaction->name);
            $this->assertDatabaseHas('transactions', [
                'id' => $transactions[$index]->id,
                'name' => 'Bulk Updated '.$transactions[$index]->id,
            ]);
        }
    }

    public function test_bulk_update_rolls_back_on_authorization_failure(): void
    {
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();
        $category = Category::factory()->create();

        $ownTransaction = Transaction::factory()->create(['user_id' => $clientA->id, 'name' => 'Original A']);
        $otherTransaction = Transaction::factory()->create(['user_id' => $clientB->id, 'name' => 'Original B']);

        $updateData = [
            ['id' => $ownTransaction->id, 'name' => 'Updated A', 'date' => '2026-01-01', 'value' => 100, 'category_id' => $category->id],
            ['id' => $otherTransaction->id, 'name' => 'Updated B', 'date' => '2026-01-01', 'value' => 100, 'category_id' => $category->id],
        ];

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        try {
            $this->service->bulkUpdate($clientA, $updateData);
        } finally {
            $this->assertDatabaseHas('transactions', ['id' => $ownTransaction->id, 'name' => 'Original A']);
            $this->assertDatabaseHas('transactions', ['id' => $otherTransaction->id, 'name' => 'Original B']);
        }
    }

    public function test_bulk_update_does_not_change_user_id_or_document_id(): void
    {
        $client = User::factory()->user()->create();
        $otherUser = User::factory()->user()->create();
        $document = Document::factory()->create();
        $category = Category::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $client->id,
            'document_id' => $document->id,
        ]);

        $updateData = [[
            'id' => $transaction->id,
            'name' => 'Updated Name',
            'date' => '2026-01-01',
            'value' => 100,
            'category_id' => $category->id,
            'user_id' => $otherUser->id,
            'document_id' => null,
        ]];

        $this->service->bulkUpdate($client, $updateData);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $client->id,
            'document_id' => $document->id,
        ]);
    }

    public function test_bulk_update_returns_transactions_with_category_loaded(): void
    {
        $client = User::factory()->user()->create();
        $category = Category::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id]);

        $updateData = [[
            'id' => $transaction->id,
            'name' => 'Updated',
            'date' => '2026-01-01',
            'value' => 50,
            'category_id' => $category->id,
        ]];

        $result = $this->service->bulkUpdate($client, $updateData);

        $this->assertTrue($result->first()->relationLoaded('category'));
    }
}
