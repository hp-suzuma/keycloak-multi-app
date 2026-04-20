<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAssignmentStoreControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_validation_errors_for_invalid_payloads(): void
    {
        $this->assignRole('keycloak-user-manager-assignment-store-invalid', 'tenant_user_manager');

        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');

        $response = $this->withAccessToken('keycloak-user-manager-assignment-store-invalid')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => 'invalid',
                'role_id' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope_id', 'role_id']);
    }

    public function test_it_returns_not_found_when_the_user_does_not_exist(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-assignment-store-missing-user', 'tenant_user_manager');
        $role = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-assignment-store-missing-user')
            ->postJson('/api/users/missing-user/assignments', [
                'scope_id' => $scope->id,
                'role_id' => $role->id,
            ]);

        $this->assertNotFoundResponse($response);
    }

    public function test_it_returns_not_found_when_the_target_scope_is_not_manageable(): void
    {
        $this->assignRole('keycloak-user-manager-assignment-store-hidden-scope', 'tenant_user_manager');

        $hiddenScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-hidden',
            'name' => 'Tenant Hidden',
        ]);

        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $role = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-assignment-store-hidden-scope')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $hiddenScope->id,
                'role_id' => $role->id,
            ]);

        $this->assertNotFoundResponse($response);
    }

    public function test_it_returns_validation_failed_when_the_role_layer_does_not_match_the_scope_layer(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-assignment-store-layer', 'tenant_user_manager');
        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $role = Role::query()->where('slug', 'server_user_manager')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-assignment-store-layer')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $scope->id,
                'role_id' => $role->id,
            ]);

        $this->assertValidationFailedResponse($response, [
            'role_id' => ['The selected role does not match the target scope layer.'],
        ]);
    }

    public function test_it_creates_an_assignment_in_a_visible_scope(): void
    {
        $serverScope = $this->assignRole('keycloak-user-manager-assignment-store', 'server_user_manager');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $role = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();
        $serverUserManagerRole = Role::query()->where('slug', 'server_user_manager')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-assignment-store')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $tenantScope->id,
                'role_id' => $role->id,
            ]);

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => $this->userPayload(
                    'managed-user',
                    'Managed User',
                    'managed@example.com',
                    [
                        $this->assignmentPayload(
                            $this->assignmentIdOf('managed-user', $tenantScope->id, $role->id),
                            $tenantScope,
                            $role,
                        ),
                    ],
                    ['object.read'],
                ),
            ]);

        $this->assertDatabaseHas('user_role_assignments', [
            'keycloak_sub' => 'managed-user',
            'scope_id' => $tenantScope->id,
            'role_id' => $role->id,
        ]);

        $managerResponse = $this->withAccessToken('keycloak-user-manager-assignment-store')
            ->getJson('/api/users/keycloak-user-manager-assignment-store');

        $managerResponse
            ->assertOk()
            ->assertExactJson([
                'data' => $this->userPayload(
                    'keycloak-user-manager-assignment-store',
                    'AP User',
                    'keycloak-user-manager-assignment-store@example.com',
                    [
                        $this->assignmentPayload(
                            $this->assignmentIdOf('keycloak-user-manager-assignment-store', $serverScope->id, $serverUserManagerRole->id),
                            $serverScope,
                            $serverUserManagerRole,
                        ),
                    ],
                    ['user.manage'],
                ),
            ]);
    }

    public function test_it_returns_validation_failed_when_the_assignment_already_exists(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-assignment-store-duplicate', 'tenant_user_manager');
        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $role = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $this->assignManagedRole($user, 'tenant_viewer', $scope);

        $response = $this->withAccessToken('keycloak-user-manager-assignment-store-duplicate')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $scope->id,
                'role_id' => $role->id,
            ]);

        $this->assertValidationFailedResponse($response, [
            'assignment' => ['The assignment already exists.'],
        ]);
    }

    public function test_it_requires_the_user_manage_permission(): void
    {
        $scope = $this->assignRole('keycloak-user-assignment-store-without-manage', 'tenant_viewer');
        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $role = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-assignment-store-without-manage')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $scope->id,
                'role_id' => $role->id,
            ]);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['user.manage'],
            ]);
    }

    public function test_it_changes_an_assignment_by_deleting_and_recreating_it(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-assignment-reassign', 'tenant_user_manager');
        $user = $this->createManagedUser('managed-user-reassign', 'Managed User', 'managed-reassign@example.com');
        $viewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();
        $operatorRole = Role::query()->where('slug', 'tenant_operator')->firstOrFail();

        $this->assignManagedRole($user, 'tenant_viewer', $scope);
        $assignmentId = $this->assignmentIdOf('managed-user-reassign', $scope->id, $viewerRole->id);

        $deleteResponse = $this->withAccessToken('keycloak-user-manager-assignment-reassign')
            ->deleteJson('/api/users/'.$user->keycloak_sub.'/assignments/'.$assignmentId);

        $deleteResponse->assertNoContent();

        $storeResponse = $this->withAccessToken('keycloak-user-manager-assignment-reassign')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $scope->id,
                'role_id' => $operatorRole->id,
            ]);

        $storeResponse
            ->assertCreated()
            ->assertExactJson([
                'data' => $this->userPayload(
                    'managed-user-reassign',
                    'Managed User',
                    'managed-reassign@example.com',
                    [
                        $this->assignmentPayload(
                            $this->assignmentIdOf('managed-user-reassign', $scope->id, $operatorRole->id),
                            $scope,
                            $operatorRole,
                        ),
                    ],
                    ['object.read', 'object.update', 'object.execute'],
                ),
            ]);

        $this->assertDatabaseHas('user_role_assignments', [
            'keycloak_sub' => 'managed-user-reassign',
            'scope_id' => $scope->id,
            'role_id' => $viewerRole->id,
            'is_deleted' => true,
        ]);

        $this->assertDatabaseHas('user_role_assignments', [
            'keycloak_sub' => 'managed-user-reassign',
            'scope_id' => $scope->id,
            'role_id' => $operatorRole->id,
            'is_deleted' => false,
        ]);
    }

    public function test_it_builds_multiple_visible_assignments_through_repeated_single_assignment_requests(): void
    {
        $serverScope = $this->assignRole('keycloak-user-manager-assignment-sequential', 'server_user_manager');

        $serviceScope = Scope::query()->create([
            'layer' => 'service',
            'code' => 'svc-a',
            'name' => 'Service A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serviceScope->id,
        ]);

        $user = $this->createManagedUser('managed-user-sequential', 'Managed User', 'managed-sequential@example.com');
        $serviceViewerRole = Role::query()->where('slug', 'service_viewer')->firstOrFail();
        $tenantOperatorRole = Role::query()->where('slug', 'tenant_operator')->firstOrFail();

        $this->withAccessToken('keycloak-user-manager-assignment-sequential')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $serviceScope->id,
                'role_id' => $serviceViewerRole->id,
            ])
            ->assertCreated();

        $this->withAccessToken('keycloak-user-manager-assignment-sequential')
            ->postJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $tenantScope->id,
                'role_id' => $tenantOperatorRole->id,
            ])
            ->assertCreated();

        $response = $this->withAccessToken('keycloak-user-manager-assignment-sequential')
            ->getJson('/api/users/'.$user->keycloak_sub);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => $this->userPayload(
                    'managed-user-sequential',
                    'Managed User',
                    'managed-sequential@example.com',
                    [
                        $this->assignmentPayload(
                            $this->assignmentIdOf('managed-user-sequential', $serviceScope->id, $serviceViewerRole->id),
                            $serviceScope,
                            $serviceViewerRole,
                        ),
                        $this->assignmentPayload(
                            $this->assignmentIdOf('managed-user-sequential', $tenantScope->id, $tenantOperatorRole->id),
                            $tenantScope,
                            $tenantOperatorRole,
                        ),
                    ],
                    ['object.read', 'object.update', 'object.execute'],
                ),
            ]);
    }

    private function createManagedUser(string $keycloakSub, string $displayName, string $email): ApUser
    {
        return ApUser::query()->create([
            'keycloak_sub' => $keycloakSub,
            'display_name' => $displayName,
            'email' => $email,
        ]);
    }

    private function assignManagedRole(ApUser $user, string $roleSlug, Scope $scope): void
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        UserRoleAssignment::query()->create([
            'keycloak_sub' => $user->keycloak_sub,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
        ]);
    }

    private function assignmentIdOf(string $keycloakSub, int $scopeId, int $roleId): int
    {
        return UserRoleAssignment::query()
            ->where('keycloak_sub', $keycloakSub)
            ->where('scope_id', $scopeId)
            ->where('role_id', $roleId)
            ->valueOrFail('id');
    }

    private function assignmentPayload(int $id, Scope $scope, Role $role): array
    {
        return [
            'id' => $id,
            'scope' => [
                'id' => $scope->id,
                'layer' => $scope->layer,
                'code' => $scope->code,
                'name' => $scope->name,
                'parent_scope_id' => $scope->parent_scope_id,
            ],
            'role' => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'scope_layer' => $role->scope_layer,
                'permission_role' => $role->permission_role,
            ],
            'permissions' => $role->permissions
                ->sortBy('id')
                ->values()
                ->map(fn ($permission): array => [
                    'id' => $permission->id,
                    'slug' => $permission->slug,
                    'name' => $permission->name,
                ])
                ->all(),
        ];
    }

    private function userPayload(
        string $keycloakSub,
        string $displayName,
        string $email,
        array $assignments,
        array $permissions,
    ): array {
        return [
            'keycloak_sub' => $keycloakSub,
            'display_name' => $displayName,
            'email' => $email,
            'assignments' => $assignments,
            'permissions' => $permissions,
        ];
    }

    private function assertNotFoundResponse($response): void
    {
        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Not Found',
            ]);
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
