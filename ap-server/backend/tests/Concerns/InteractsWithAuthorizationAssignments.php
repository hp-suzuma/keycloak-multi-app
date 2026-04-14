<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;

trait InteractsWithAuthorizationAssignments
{
    protected function createDefaultScopeForRole(string $keycloakSub, string $roleSlug): Scope
    {
        return Scope::query()->create([
            'layer' => str($roleSlug)->before('_')->value(),
            'code' => $roleSlug.'-scope-'.$keycloakSub,
            'name' => $roleSlug.' scope',
        ]);
    }

    protected function createUserRoleAssignment(string $keycloakSub, string $roleSlug, Scope $scope): void
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        UserRoleAssignment::query()->create([
            'keycloak_sub' => $keycloakSub,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
        ]);
    }
}
