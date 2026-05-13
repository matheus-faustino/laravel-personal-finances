<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_gets_client_role_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Role::Client, $user->role);
    }

    public function test_factory_admin_state_produces_admin_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(Role::Admin, $user->role);
    }

    public function test_factory_client_state_produces_client_user(): void
    {
        $user = User::factory()->client()->create();

        $this->assertSame(Role::Client, $user->role);
    }

    public function test_is_admin_returns_true_for_admin_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isClient());
    }

    public function test_is_client_returns_true_for_client_user(): void
    {
        $client = User::factory()->client()->create();

        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isAdmin());
    }

    public function test_role_is_included_in_api_user_response(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/user');

        $response->assertOk()->assertJsonFragment(['role' => Role::Client->value]);
    }
}
