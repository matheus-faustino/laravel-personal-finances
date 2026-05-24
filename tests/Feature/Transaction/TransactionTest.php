<?php

namespace Tests\Feature\Transaction;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Invoice Payment',
            'description' => 'Monthly invoice',
            'date' => '2026-01-15',
            'value' => 100.00,
            'category_id' => Category::factory()->create()->id,
        ], $overrides);
    }

    // --- index ---

    public function test_admin_can_list_all_transactions(): void
    {
        $admin = User::factory()->admin()->create();
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Transaction::factory()->count(2)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(3)->create(['user_id' => $clientB->id]);

        $this->actingAs($admin)->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_client_can_only_list_their_own_transactions(): void
    {
        $clientA = User::factory()->user()->create();
        $clientB = User::factory()->user()->create();

        Transaction::factory()->count(3)->create(['user_id' => $clientA->id]);
        Transaction::factory()->count(2)->create(['user_id' => $clientB->id]);

        $this->actingAs($clientA)->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_unauthenticated_user_cannot_list_transactions(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
    }

    // --- index filters ---

    public function test_index_filters_transactions_by_start_date(): void
    {
        $client = User::factory()->user()->create();

        Transaction::factory()->create(['user_id' => $client->id, 'date' => '2026-01-15']);
        Transaction::factory()->create(['user_id' => $client->id, 'date' => '2026-03-10']);

        $this->actingAs($client)->getJson('/api/transactions?start_date=2026-02-01')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_transactions_by_end_date(): void
    {
        $client = User::factory()->user()->create();

        Transaction::factory()->create(['user_id' => $client->id, 'date' => '2026-01-15']);
        Transaction::factory()->create(['user_id' => $client->id, 'date' => '2026-03-10']);

        $this->actingAs($client)->getJson('/api/transactions?end_date=2026-02-28')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_transactions_by_date_range(): void
    {
        $client = User::factory()->user()->create();

        Transaction::factory()->create(['user_id' => $client->id, 'date' => '2025-12-01']);
        Transaction::factory()->create(['user_id' => $client->id, 'date' => '2026-01-15']);
        Transaction::factory()->create(['user_id' => $client->id, 'date' => '2026-03-10']);

        $this->actingAs($client)->getJson('/api/transactions?start_date=2026-01-01&end_date=2026-01-31')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_transactions_by_category_id(): void
    {
        $client = User::factory()->user()->create();
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();

        Transaction::factory()->count(3)->create(['user_id' => $client->id, 'category_id' => $catA->id]);
        Transaction::factory()->count(2)->create(['user_id' => $client->id, 'category_id' => $catB->id]);

        $this->actingAs($client)->getJson("/api/transactions?category_id={$catA->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_rejects_nonexistent_category_id(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->getJson('/api/transactions?category_id=99999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_index_paginates_transactions_with_custom_per_page(): void
    {
        $client = User::factory()->user()->create();
        Transaction::factory()->count(10)->create(['user_id' => $client->id]);

        $this->actingAs($client)->getJson('/api/transactions?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['total' => 10]);
    }

    public function test_index_rejects_invalid_per_page(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->getJson('/api/transactions?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_index_rejects_invalid_date_format(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->getJson('/api/transactions?start_date=15-01-2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_index_rejects_end_date_before_start_date(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->getJson('/api/transactions?start_date=2026-03-01&end_date=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    // --- show ---

    public function test_admin_can_show_any_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->create(['name' => 'Admin Viewed']);

        $this->actingAs($admin)->getJson("/api/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Admin Viewed']);
    }

    public function test_client_can_show_their_own_transaction(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id, 'name' => 'My Transaction']);

        $this->actingAs($client)->getJson("/api/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'My Transaction']);
    }

    public function test_client_cannot_show_another_clients_transaction(): void
    {
        $client = User::factory()->user()->create();
        $other = Transaction::factory()->create();

        $this->actingAs($client)->getJson("/api/transactions/{$other->id}")->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_show_a_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $this->getJson("/api/transactions/{$transaction->id}")->assertUnauthorized();
    }

    public function test_show_returns_404_for_unknown_transaction(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/transactions/999')->assertNotFound();
    }

    // --- store ---

    public function test_client_can_create_their_own_transaction(): void
    {
        $client = User::factory()->user()->create();
        $payload = $this->validPayload();

        $this->actingAs($client)->postJson('/api/transactions', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('transactions', ['name' => $payload['name'], 'user_id' => $client->id]);
    }

    public function test_user_id_is_always_assigned_from_the_authenticated_user(): void
    {
        $client = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $payload = $this->validPayload(['user_id' => $other->id]);

        $this->actingAs($client)->postJson('/api/transactions', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('transactions', ['name' => $payload['name'], 'user_id' => $client->id]);
        $this->assertDatabaseMissing('transactions', ['name' => $payload['name'], 'user_id' => $other->id]);
    }

    public function test_admin_cannot_create_a_transaction(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/transactions', $this->validPayload())
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_a_transaction(): void
    {
        $this->postJson('/api/transactions', $this->validPayload())->assertUnauthorized();
    }

    public function test_store_requires_name(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/transactions', $this->validPayload(['name' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_date(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/transactions', $this->validPayload(['date' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_store_requires_value(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/transactions', $this->validPayload(['value' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_store_rejects_negative_value(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/transactions', $this->validPayload(['value' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_store_requires_category_id(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/transactions', $this->validPayload(['category_id' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_store_validates_category_id_exists(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/transactions', $this->validPayload(['category_id' => 99999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_store_accepts_nullable_description(): void
    {
        $client = User::factory()->user()->create();
        $payload = $this->validPayload(['description' => null]);

        $this->actingAs($client)->postJson('/api/transactions', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('transactions', ['name' => $payload['name'], 'description' => null]);
    }

    // --- update ---

    public function test_client_can_update_their_own_transaction(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id, 'name' => 'Old Name']);
        $category = Category::factory()->create();

        $this->actingAs($client)->putJson("/api/transactions/{$transaction->id}", [
            'name' => 'Updated Name',
            'date' => '2026-02-01',
            'value' => 150.00,
            'category_id' => $category->id,
        ])->assertOk()->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'name' => 'Updated Name']);
    }

    public function test_client_cannot_update_another_clients_transaction(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['name' => 'Original']);
        $category = Category::factory()->create();

        $this->actingAs($client)->putJson("/api/transactions/{$transaction->id}", [
            'name' => 'Hacked',
            'date' => '2026-02-01',
            'value' => 1.00,
            'category_id' => $category->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'name' => 'Original']);
    }

    public function test_admin_cannot_update_a_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->create(['name' => 'Original']);
        $category = Category::factory()->create();

        $this->actingAs($admin)->putJson("/api/transactions/{$transaction->id}", [
            'name' => 'Changed',
            'date' => '2026-02-01',
            'value' => 1.00,
            'category_id' => $category->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'name' => 'Original']);
    }

    public function test_unauthenticated_user_cannot_update_a_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $this->putJson("/api/transactions/{$transaction->id}", ['name' => 'X'])->assertUnauthorized();
    }

    public function test_update_requires_name(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->putJson("/api/transactions/{$transaction->id}", [
            'date' => '2026-02-01',
            'value' => 50.00,
            'category_id' => $transaction->category_id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_update_requires_date(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->putJson("/api/transactions/{$transaction->id}", [
            'name' => 'Test',
            'value' => 50.00,
            'category_id' => $transaction->category_id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['date']);
    }

    public function test_update_requires_value(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->putJson("/api/transactions/{$transaction->id}", [
            'name' => 'Test',
            'date' => '2026-02-01',
            'category_id' => $transaction->category_id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['value']);
    }

    public function test_update_requires_category_id(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->putJson("/api/transactions/{$transaction->id}", [
            'name' => 'Test',
            'date' => '2026-02-01',
            'value' => 50.00,
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    public function test_update_validates_category_id_exists(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->putJson("/api/transactions/{$transaction->id}", [
            'name' => 'Test',
            'date' => '2026-02-01',
            'value' => 50.00,
            'category_id' => 99999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    // --- destroy ---

    public function test_client_can_delete_their_own_transaction(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->deleteJson("/api/transactions/{$transaction->id}")->assertNoContent();
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_client_cannot_delete_another_clients_transaction(): void
    {
        $client = User::factory()->user()->create();
        $transaction = Transaction::factory()->create();

        $this->actingAs($client)->deleteJson("/api/transactions/{$transaction->id}")->assertForbidden();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_admin_cannot_delete_a_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/transactions/{$transaction->id}")->assertForbidden();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_unauthenticated_user_cannot_delete_a_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $this->deleteJson("/api/transactions/{$transaction->id}")->assertUnauthorized();
    }
}
