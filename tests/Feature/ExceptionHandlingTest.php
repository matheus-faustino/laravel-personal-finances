<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    public function test_model_not_found_returns_json_404(): void
    {
        $response = $this->get('/api/email/verify/99999/fakehash');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Resource not found.']);
    }

    public function test_model_not_found_returns_json_without_accept_header(): void
    {
        $response = $this->get('/api/email/verify/99999/fakehash');

        $response->assertStatus(404)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_unauthenticated_returns_json_401(): void
    {
        $response = $this->get('/api/user');

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_validation_error_returns_json_422(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }
}
