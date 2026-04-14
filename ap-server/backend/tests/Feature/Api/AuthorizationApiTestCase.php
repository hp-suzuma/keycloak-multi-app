<?php

namespace Tests\Feature\Api;

use Database\Seeders\AuthorizationSeeder;
use Tests\Concerns\InteractsWithAuthorizationAssignments;

abstract class AuthorizationApiTestCase extends KeycloakApiTestCase
{
    use InteractsWithAuthorizationAssignments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }
}
