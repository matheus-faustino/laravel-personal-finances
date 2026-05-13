<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'token_type']);
        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()->assertJson(['message' => __('auth.invalid_credentials')]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_unverified_email(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertForbidden()->assertJson(['message' => __('auth.email_not_verified')]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_previous_tokens_are_revoked_on_login(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');
        $user->createToken('api-token');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');

        $response->assertOk()->assertJson(['message' => __('auth.logout_success')]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertUnauthorized();
    }
}
