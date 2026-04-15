<?php

namespace Tests\Feature\Api;

abstract class UpsertAuthorizationApiTestCase extends AuthorizationApiTestCase
{
    protected function prepareAuthorizationUser(string $keycloakSub): void
    {
        $this->updateOrCreateAuthorizationUser($keycloakSub);
    }
}
