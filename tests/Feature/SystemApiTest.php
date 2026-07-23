<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_and_api_errors_are_json_with_security_and_correlation_headers(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Correlation-ID');

        $this->getJson('/api/route-that-does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_login_is_rate_limited(): void
    {
        $payload = ['email' => 'rate-limit@example.com', 'password' => 'incorrecta'];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/login', $payload)
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }
}
