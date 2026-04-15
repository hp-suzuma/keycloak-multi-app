<?php

namespace Tests\Feature\Api;

use App\Models\ManagedObject;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ObjectStoreControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_validation_errors_for_invalid_payloads(): void
    {
        $scope = $this->assignRole('keycloak-user-1', 'tenant_admin');

        $response = $this->withAccessToken('keycloak-user-1')
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
        $accessibleScope = $this->createTenantScope('tenant-a', 'Tenant A');

        $forbiddenScope = $this->createTenantScope('tenant-b', 'Tenant B');

        $this->assignRole('keycloak-user-2', 'tenant_admin', $accessibleScope);

        $response = $this->withAccessToken('keycloak-user-2')
            ->postJson('/api/objects', [
                'scope_id' => $forbiddenScope->id,
                'code' => 'object-b',
                'name' => 'Object B',
            ]);

        $this->assertForbiddenResponse($response, ['object.create'], $forbiddenScope->id);
    }

    public function test_it_creates_an_object_when_the_target_scope_is_accessible(): void
    {
        $serverScope = $this->assignRole('keycloak-user-3', 'server_admin');

        $tenantScope = $this->createTenantScope('tenant-a', 'Tenant A', $serverScope->id);

        $response = $this->withAccessToken('keycloak-user-3')
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

        $response = $this->withAccessToken('keycloak-user-duplicate')
            ->postJson('/api/objects', [
                'scope_id' => $scope->id,
                'code' => '  Duplicated_Code  ',
                'name' => 'New Object',
            ]);

        $this->assertDuplicateCodeValidationResponse($response);
    }

    /**
     * @param  array<int, string>  $requiredPermissions
     */
    private function assertForbiddenResponse($response, array $requiredPermissions, int $scopeId): void
    {
        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => $requiredPermissions,
                'scope_id' => $scopeId,
            ]);
    }

    private function assertDuplicateCodeValidationResponse($response): void
    {
        $this->assertValidationFailedResponse($response, [
            'code' => ['The code has already been taken within the target scope.'],
        ]);
    }

    private function createTenantScope(string $code, string $name, ?int $parentScopeId = null): Scope
    {
        return Scope::query()->create(array_filter([
            'layer' => 'tenant',
            'code' => $code,
            'name' => $name,
            'parent_scope_id' => $parentScopeId,
        ], static fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function assertValidationFailedResponse($response, array $errors): void
    {
        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'errors' => $errors,
            ]);
    }
}
