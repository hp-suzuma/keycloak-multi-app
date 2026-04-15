<?php

namespace Tests\Feature\Api;

use App\Models\ManagedObject;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ObjectUpdateControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_validation_errors_for_invalid_payloads(): void
    {
        $scope = $this->assignRole('keycloak-user-1', 'tenant_operator');

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'object-a',
            'name' => 'Object A',
        ]);

        $response = $this->withAccessToken('keycloak-user-1')
            ->patchJson('/api/objects/'.$managedObject->id, [
                'name' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_returns_validation_errors_when_no_fields_are_provided(): void
    {
        $scope = $this->assignRole('keycloak-user-empty', 'tenant_operator');

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'object-empty',
            'name' => 'Object Empty',
        ]);

        $response = $this->withAccessToken('keycloak-user-empty')
            ->patchJson('/api/objects/'.$managedObject->id, []);

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'errors' => [
                    'payload' => ['At least one of scope_id, code, or name is required.'],
                ],
            ]);
    }

    public function test_it_returns_not_found_when_the_object_does_not_exist(): void
    {
        $this->assignRole('keycloak-user-2', 'tenant_operator');

        $response = $this->withAccessToken('keycloak-user-2')
            ->patchJson('/api/objects/999999', [
                'name' => 'Updated Name',
            ]);

        $this->assertNotFoundResponse($response);
    }

    public function test_it_returns_forbidden_when_the_object_scope_is_not_accessible(): void
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

        $this->assignRole('keycloak-user-3', 'tenant_operator', $accessibleScope);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $forbiddenScope->id,
            'code' => 'object-b',
            'name' => 'Object B',
        ]);

        $response = $this->withAccessToken('keycloak-user-3')
            ->patchJson('/api/objects/'.$managedObject->id, [
                'name' => 'Updated Object B',
            ]);

        $this->assertForbiddenResponse($response, ['object.update'], $forbiddenScope->id);
    }

    public function test_it_updates_the_object_when_the_scope_is_accessible(): void
    {
        $serverScope = $this->assignRole('keycloak-user-4', 'server_operator');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'object-c',
            'name' => 'Object C',
        ]);

        $response = $this->withAccessToken('keycloak-user-4')
            ->patchJson('/api/objects/'.$managedObject->id, [
                'code' => ' Updated_Object_C ',
                'name' => 'Updated Object C',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $managedObject->id,
                    'scope_id' => $tenantScope->id,
                    'code' => 'updated-object-c',
                    'name' => 'Updated Object C',
                ],
            ]);

        $this->assertSame('Updated Object C', $managedObject->fresh()->name);
        $this->assertSame('updated-object-c', $managedObject->fresh()->code);
    }

    public function test_it_moves_the_object_when_the_user_can_update_current_scope_and_create_in_target_scope(): void
    {
        $currentScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-current',
            'name' => 'Tenant Current',
        ]);

        $targetScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-target',
            'name' => 'Tenant Target',
        ]);

        $this->assignRole('keycloak-user-move', 'tenant_operator', $currentScope);
        $this->assignRole('keycloak-user-move', 'tenant_admin', $targetScope);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $currentScope->id,
            'code' => 'object-move',
            'name' => 'Object Move',
        ]);

        $response = $this->withAccessToken('keycloak-user-move')
            ->patchJson('/api/objects/'.$managedObject->id, [
                'scope_id' => $targetScope->id,
                'name' => 'Moved Object',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $managedObject->id,
                    'scope_id' => $targetScope->id,
                    'code' => 'object-move',
                    'name' => 'Moved Object',
                ],
            ]);

        $this->assertDatabaseHas('objects', [
            'id' => $managedObject->id,
            'scope_id' => $targetScope->id,
            'name' => 'Moved Object',
        ]);
    }

    public function test_it_returns_forbidden_when_the_user_cannot_create_in_the_target_scope_for_move(): void
    {
        $currentScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-current',
            'name' => 'Tenant Current',
        ]);

        $targetScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-target',
            'name' => 'Tenant Target',
        ]);

        $this->assignRole('keycloak-user-move-fail', 'tenant_operator', $currentScope);
        $this->assignRole('keycloak-user-move-fail', 'tenant_viewer', $targetScope);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $currentScope->id,
            'code' => 'object-move-fail',
            'name' => 'Object Move Fail',
        ]);

        $response = $this->withAccessToken('keycloak-user-move-fail')
            ->patchJson('/api/objects/'.$managedObject->id, [
                'scope_id' => $targetScope->id,
            ]);

        $this->assertForbiddenResponse($response, ['object.create'], $targetScope->id);
    }

    public function test_it_returns_validation_errors_when_the_target_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-dup-update', 'tenant_admin');

        ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'duplicated-code',
            'name' => 'Existing Object',
        ]);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'editable-code',
            'name' => 'Editable Object',
        ]);

        $response = $this->withAccessToken('keycloak-user-dup-update')
            ->patchJson('/api/objects/'.$managedObject->id, [
                'code' => ' Duplicated_Code ',
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

    private function assertNotFoundResponse($response): void
    {
        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Not Found',
            ]);
    }
}
