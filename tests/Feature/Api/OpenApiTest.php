<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_document_is_served_and_valid(): void
    {
        $res = $this->getJson('/api/v1/openapi.json');

        $res->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'Muhindo Mubaraka API')
            ->assertJsonStructure([
                'openapi', 'info', 'servers',
                'components' => ['securitySchemes' => ['bearerAuth'], 'schemas' => ['Envelope']],
                'paths' => ['/auth/login', '/courses', '/my/projects', '/invoices'],
            ]);
    }

    public function test_openapi_is_public_no_token_needed(): void
    {
        $this->getJson('/api/v1/openapi.json')->assertOk();
    }
}
