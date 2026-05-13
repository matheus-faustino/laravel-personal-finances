<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->setLocale(config('app.locale'));

        parent::tearDown();
    }

    private function attemptLoginWithInvalidCredentials(array $headers = []): TestResponse
    {
        return $this->withHeaders($headers)->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ]);
    }

    public function test_default_locale_returns_english_messages(): void
    {
        $response = $this->attemptLoginWithInvalidCredentials();

        $response->assertUnauthorized()->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_pt_br_header_returns_portuguese_messages(): void
    {
        $response = $this->attemptLoginWithInvalidCredentials(['Accept-Language' => 'pt-BR']);

        $response->assertUnauthorized()->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_pt_header_normalizes_to_pt_br(): void
    {
        $response = $this->attemptLoginWithInvalidCredentials(['Accept-Language' => 'pt']);

        $response->assertUnauthorized()->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_unknown_locale_falls_back_to_english(): void
    {
        $response = $this->attemptLoginWithInvalidCredentials(['Accept-Language' => 'fr']);

        $response->assertUnauthorized()->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_quality_weighted_pt_br_header_returns_portuguese(): void
    {
        $response = $this->attemptLoginWithInvalidCredentials([
            'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
        ]);

        $response->assertUnauthorized()->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_validation_errors_are_localized_for_pt_br(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'pt-BR'])
            ->postJson('/api/auth/login', []);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'O campo email é obrigatório.');
    }

    public function test_validation_errors_are_in_english_by_default(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.email.0', 'The email field is required.');
    }

    public function test_pt_br_unverified_email_message_is_translated(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->withHeaders(['Accept-Language' => 'pt-BR'])
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertForbidden()->assertJson(['message' => 'Por favor, verifique seu endereço de e-mail.']);
    }
}
