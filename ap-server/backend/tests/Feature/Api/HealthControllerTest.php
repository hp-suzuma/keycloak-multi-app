<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_it_returns_an_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }
}
