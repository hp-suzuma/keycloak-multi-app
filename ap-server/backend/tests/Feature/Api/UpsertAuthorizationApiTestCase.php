<?php

namespace Tests\Feature\Api;

use App\Models\Scope;

abstract class UpsertAuthorizationApiTestCase extends AuthorizationApiTestCase
{
    protected function assignRole(string $keycloakSub, string $roleSlug, ?Scope $scope = null): Scope
    {
        $this->updateOrCreateAuthorizationUser($keycloakSub);
        $scope ??= $this->createDefaultScopeForRole($keycloakSub, $roleSlug);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);

        return $scope;
    }
}
