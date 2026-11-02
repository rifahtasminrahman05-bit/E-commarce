<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_api_health_returns_the_versioned_success_contract(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertSee('"meta":{}', false)
            ->assertJsonStructure(['message', 'data', 'meta']);
    }

    public function test_unknown_api_route_returns_the_versioned_error_contract(): void
    {
        $this->getJson('/api/v1/not-found')
            ->assertNotFound()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertSee('"errors":{}', false)
            ->assertJsonStructure(['message', 'errors', 'code', 'request_id']);
    }
}
