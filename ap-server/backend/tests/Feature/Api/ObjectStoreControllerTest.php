<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\ManagedObject;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ObjectStoreControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_validation_errors_for_invalid_payloads(): void
    {
        $scope = $this->assignRole('keycloak-user-1', 'tenant_admin');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-1'))
            ->postJson('/api/objects', [
                'scope_id' => $scope->id,
                'code' => '',
                'name' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_it_returns_forbidden_when_the_target_scope_is_not_accessible(): void
    {
        $accessibleScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
        ]);

        $forbiddenScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-b',
            'name' => 'Tenant B',
        ]);

        $this->assignRole('keycloak-user-2', 'tenant_admin', $accessibleScope);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-2'))
            ->postJson('/api/objects', [
                'scope_id' => $forbiddenScope->id,
                'code' => 'object-b',
                'name' => 'Object B',
            ]);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.create'],
                'scope_id' => $forbiddenScope->id,
            ]);
    }

    public function test_it_creates_an_object_when_the_target_scope_is_accessible(): void
    {
        $serverScope = $this->assignRole('keycloak-user-3', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-3'))
            ->postJson('/api/objects', [
                'scope_id' => $tenantScope->id,
                'code' => ' Object_C ',
                'name' => 'Object C',
            ]);

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => 1,
                    'scope_id' => $tenantScope->id,
                    'code' => 'object-c',
                    'name' => 'Object C',
                ],
            ]);

        $this->assertDatabaseHas('objects', [
            'scope_id' => $tenantScope->id,
            'code' => 'object-c',
            'name' => 'Object C',
        ]);
    }

    public function test_it_returns_validation_errors_when_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-duplicate', 'tenant_admin');

        ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'duplicated-code',
            'name' => 'Existing Object',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-duplicate'))
            ->postJson('/api/objects', [
                'scope_id' => $scope->id,
                'code' => '  Duplicated_Code  ',
                'name' => 'New Object',
            ]);

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'errors' => [
                    'code' => ['The code has already been taken within the target scope.'],
                ],
            ]);
    }

    private function assignRole(string $keycloakSub, string $roleSlug, ?Scope $scope = null): Scope
    {
        ApUser::query()->create([
            'keycloak_sub' => $keycloakSub,
            'display_name' => 'AP User',
            'email' => $keycloakSub.'@example.com',
        ]);

        $scope ??= Scope::query()->create([
            'layer' => str($roleSlug)->before('_')->value(),
            'code' => $roleSlug.'-scope-'.$keycloakSub,
            'name' => $roleSlug.' scope',
        ]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        UserRoleAssignment::query()->create([
            'keycloak_sub' => $keycloakSub,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
        ]);

        return $scope;
    }

}
