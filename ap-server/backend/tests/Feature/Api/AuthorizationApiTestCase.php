<?php

namespace Tests\Feature\Api;

use App\Models\Scope;
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

    protected function assignRole(string $keycloakSub, string $roleSlug, ?Scope $scope = null): Scope
    {
        $this->prepareAuthorizationUser($keycloakSub);
        $scope ??= $this->createDefaultScopeForRole($keycloakSub, $roleSlug);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);

        return $scope;
    }

    protected function prepareAuthorizationUser(string $keycloakSub): void
    {
        // Direct subclasses that do not rely on assignRole() can keep the default no-op.
    }
}
