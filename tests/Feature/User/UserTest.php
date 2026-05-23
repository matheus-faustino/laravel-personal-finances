<?php

namespace Tests\Feature\User;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_admin_can_list_all_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_client_cannot_list_users(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->getJson('/api/users')->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    // --- show ---

    public function test_admin_can_view_any_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/users/{$user->id}");

        $response->assertOk()->assertJsonFragment(['email' => $user->email]);
    }

    public function test_client_can_view_own_profile(): void
    {
        $client = User::factory()->user()->create();

        $response = $this->actingAs($client)->getJson("/api/users/{$client->id}");

        $response->assertOk()->assertJsonFragment(['email' => $client->email]);
    }

    public function test_client_cannot_view_another_users_profile(): void
    {
        $client = User::factory()->user()->create();
        $other = User::factory()->create();

        $this->actingAs($client)->getJson("/api/users/{$other->id}")->assertForbidden();
    }

    public function test_show_returns_404_for_unknown_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/users/999')->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_view_a_user(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }

    // --- store ---

    public function test_admin_can_create_a_user(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()->assertJsonFragment(['email' => 'newuser@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);

        Notification::assertSentTo(
            User::where('email', 'newuser@example.com')->first(),
            VerifyEmail::class
        );
    }

    public function test_admin_can_create_a_user_with_explicit_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'admin');
        $this->assertDatabaseHas('users', ['email' => 'newadmin@example.com', 'role' => Role::Admin->value]);
    }

    public function test_client_cannot_create_a_user(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
    }

    public function test_store_fails_with_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_store_fails_with_invalid_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'superuser',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_unauthenticated_user_cannot_create_a_user(): void
    {
        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnauthorized();
    }

    // --- update ---

    public function test_admin_can_update_any_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->putJson("/api/users/{$user->id}", [
            'name' => 'New Name',
            'email' => $user->email,
        ]);

        $response->assertOk()->assertJsonFragment(['name' => 'New Name']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();

        $response = $this->actingAs($admin)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
        ]);

        $response->assertOk()->assertJsonPath('data.role', 'admin');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => Role::Admin->value]);
    }

    public function test_client_can_update_own_profile(): void
    {
        $client = User::factory()->user()->create();

        $response = $this->actingAs($client)->putJson("/api/users/{$client->id}", [
            'name' => 'Updated Name',
            'email' => $client->email,
        ]);

        $response->assertOk()->assertJsonFragment(['name' => 'Updated Name']);
        $this->assertDatabaseHas('users', ['id' => $client->id, 'name' => 'Updated Name']);
    }

    public function test_client_cannot_update_another_users_profile(): void
    {
        $client = User::factory()->user()->create();
        $other = User::factory()->create(['name' => 'Unchanged']);

        $this->actingAs($client)->putJson("/api/users/{$other->id}", [
            'name' => 'Changed',
            'email' => $other->email,
        ])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Unchanged']);
    }

    public function test_client_cannot_change_own_role(): void
    {
        $client = User::factory()->user()->create();

        $response = $this->actingAs($client)->putJson("/api/users/{$client->id}", [
            'name' => $client->name,
            'email' => $client->email,
            'role' => 'admin',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $client->id, 'role' => Role::User->value]);
    }

    public function test_update_fails_with_email_from_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_update_passes_with_users_own_email(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'own@example.com']);

        $response = $this->actingAs($admin)->putJson("/api/users/{$user->id}", [
            'name' => 'New Name',
            'email' => 'own@example.com',
        ]);

        $response->assertOk();
    }

    public function test_client_can_update_own_password(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->putJson("/api/users/{$client->id}", [
            'name' => $client->name,
            'email' => $client->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $client->fresh()->password));
    }

    public function test_unauthenticated_user_cannot_update_a_user(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'New Name',
            'email' => $user->email,
        ])->assertUnauthorized();
    }

    // --- destroy ---

    public function test_admin_can_delete_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/users/{$user->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_client_cannot_delete_any_user(): void
    {
        $client = User::factory()->user()->create();
        $other = User::factory()->create();

        $this->actingAs($client)->deleteJson("/api/users/{$other->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_client_cannot_delete_own_account(): void
    {
        $client = User::factory()->user()->create();

        $this->actingAs($client)->deleteJson("/api/users/{$client->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $client->id]);
    }

    public function test_unauthenticated_user_cannot_delete_a_user(): void
    {
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}")->assertUnauthorized();
    }

    public function test_delete_returns_404_for_unknown_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->deleteJson('/api/users/999')->assertNotFound();
    }
}
