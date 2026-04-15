<?php

namespace Tests\Feature\Api;

abstract class CreateAuthorizationApiTestCase extends AuthorizationApiTestCase
{
    protected function prepareAuthorizationUser(string $keycloakSub): void
    {
        $this->createAuthorizationUser($keycloakSub);
    }
}
