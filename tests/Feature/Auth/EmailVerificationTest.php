<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );
    }

    public function test_user_can_verify_email_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        $response = $this->getJson($url);

        $response->assertOk()->assertJson(['message' => __('auth.email_verified')]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_fails_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'invalid-hash']
        );

        $response = $this->getJson($url);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_returns_message_when_already_verified(): void
    {
        $user = User::factory()->create();
        $url = $this->verificationUrl($user);

        $response = $this->getJson($url);

        $response->assertOk()->assertJson(['message' => __('auth.email_already_verified')]);
    }

    public function test_verification_fails_with_tampered_url(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user).'tampered';

        $response = $this->getJson($url);

        $response->assertForbidden();
    }

    public function test_resend_verification_sends_notification_to_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/email/resend-verification', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_does_not_send_to_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/email/resend-verification', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        Notification::assertNothingSent();
    }

    public function test_resend_verification_does_not_reveal_nonexistent_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/email/resend-verification', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk();
        Notification::assertNothingSent();
    }
}
