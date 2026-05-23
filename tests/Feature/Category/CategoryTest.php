<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_admin_can_list_categories(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/categories');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_client_can_list_categories(): void
    {
        $client = User::factory()->user()->create();
        Category::factory()->count(2)->create();

        $response = $this->actingAs($client)->getJson('/api/categories');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_user_cannot_list_categories(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    // --- show ---

    public function test_admin_can_show_a_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/categories/{$category->id}");

        $response->assertOk()->assertJsonFragment(['name' => $category->name]);
    }

    public function test_client_can_show_a_category(): void
    {
        $client = User::factory()->user()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($client)->getJson("/api/categories/{$category->id}");

        $response->assertOk()->assertJsonFragment(['name' => $category->name]);
    }

    public function test_unauthenticated_user_cannot_show_a_category(): void
    {
        $category = Category::factory()->create();

        $this->getJson("/api/categories/{$category->id}")->assertUnauthorized();
    }

    public function test_show_returns_404_for_unknown_category(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/categories/999')->assertNotFound();
    }

    // --- store ---

    public function test_admin_can_create_a_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/categories', ['name' => 'Electronics']);

        $response->assertCreated()->assertJsonFragment(['name' => 'Electronics']);
        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_client_cannot_create_a_category(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/categories', ['name' => 'Electronics'])->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'Electronics']);
    }

    public function test_unauthenticated_user_cannot_create_a_category(): void
    {
        $this->postJson('/api/categories', ['name' => 'Electronics'])->assertUnauthorized();
    }

    public function test_store_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/categories', [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    // --- update ---

    public function test_admin_can_update_a_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->putJson("/api/categories/{$category->id}", ['name' => 'New Name']);

        $response->assertOk()->assertJsonFragment(['name' => 'New Name']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_client_cannot_update_a_category(): void
    {
        $client = User::factory()->user()->create();
        $category = Category::factory()->create(['name' => 'Old Name']);

        $this->actingAs($client)->putJson("/api/categories/{$category->id}", ['name' => 'New Name'])->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Old Name']);
    }

    public function test_unauthenticated_user_cannot_update_a_category(): void
    {
        $category = Category::factory()->create();

        $this->putJson("/api/categories/{$category->id}", ['name' => 'New Name'])->assertUnauthorized();
    }

    public function test_update_requires_name(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->putJson("/api/categories/{$category->id}", [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    // --- destroy ---

    public function test_admin_can_delete_a_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/categories/{$category->id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_client_cannot_delete_a_category(): void
    {
        $client = User::factory()->user()->create();
        $category = Category::factory()->create();

        $this->actingAs($client)->deleteJson("/api/categories/{$category->id}")->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_unauthenticated_user_cannot_delete_a_category(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson("/api/categories/{$category->id}")->assertUnauthorized();
    }
}
