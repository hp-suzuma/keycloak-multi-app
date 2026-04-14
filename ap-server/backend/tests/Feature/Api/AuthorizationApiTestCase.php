<?php

namespace Tests\Feature\Api;

use Database\Seeders\AuthorizationSeeder;

abstract class AuthorizationApiTestCase extends KeycloakApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }
}
