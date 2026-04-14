<?php

namespace Tests\Feature\Api;

use Tests\Concerns\InteractsWithKeycloakTokens;
use Tests\TestCase;

abstract class KeycloakApiTestCase extends TestCase
{
    use InteractsWithKeycloakTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKeycloakTokenAuth();
    }
}
