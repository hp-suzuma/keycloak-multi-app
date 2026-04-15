<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserShowControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_not_found_when_the_user_does_not_exist(): void
    {
        $this->assignRole('keycloak-user-manager-show-missing', 'tenant_user_manager');

        $response = $this->withAccessToken('keycloak-user-manager-show-missing')
            ->getJson('/api/users/missing-user');

        $this->assertNotFoundResponse($response);
    }

    public function test_it_returns_not_found_when_the_user_has_no_visible_assignments(): void
    {
        $this->assignRole('keycloak-user-manager-show-hidden', 'tenant_user_manager');

        $hiddenScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-hidden',
            'name' => 'Tenant Hidden',
        ]);

        $hiddenUser = $this->createManagedUser('hidden-user', 'Hidden User', 'hidden@example.com');
        $this->assignManagedRole($hiddenUser, 'tenant_viewer', $hiddenScope);

        $response = $this->withAccessToken('keycloak-user-manager-show-hidden')
            ->getJson('/api/users/'.$hiddenUser->keycloak_sub);

        $this->assertNotFoundResponse($response);
    }

    public function test_it_returns_only_visible_assignments_for_the_user(): void
    {
        $serverScope = $this->assignRole('keycloak-user-manager-show', 'server_user_manager');

        $visibleTenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-visible',
            'name' => 'Tenant Visible',
            'parent_scope_id' => $serverScope->id,
        ]);

        $hiddenServerScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-hidden',
            'name' => 'Server Hidden',
        ]);

        $managedUser = $this->createManagedUser('visible-user', 'Visible User', 'visible@example.com');
        $this->assignManagedRole($managedUser, 'tenant_viewer', $visibleTenantScope);
        $this->assignManagedRole($managedUser, 'server_admin', $hiddenServerScope);

        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-show')
            ->getJson('/api/users/'.$managedUser->keycloak_sub);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => $this->userPayload(
                    'visible-user',
                    'Visible User',
                    'visible@example.com',
                    [
                        $this->assignmentPayload(
                            $this->assignmentIdOf('visible-user', $visibleTenantScope->id, $tenantViewerRole->id),
                            $visibleTenantScope,
                            $tenantViewerRole,
                            ['object.read'],
                        ),
                    ],
                    ['object.read'],
                ),
            ]);
    }

    public function test_it_requires_the_user_manage_permission(): void
    {
        $scope = $this->assignRole('keycloak-user-show-without-manage', 'tenant_viewer');

        $managedUser = $this->createManagedUser('visible-user', 'Visible User', 'visible@example.com');
        $this->assignManagedRole($managedUser, 'tenant_viewer', $scope);

        $response = $this->withAccessToken('keycloak-user-show-without-manage')
            ->getJson('/api/users/'.$managedUser->keycloak_sub);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['user.manage'],
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

    private function permissionPayload(string $slug): array
    {
        $permission = Permission::query()->where('slug', $slug)->firstOrFail();

        return [
            'id' => $permission->id,
            'slug' => $permission->slug,
            'name' => $permission->name,
        ];
    }

    private function assignmentPayload(int $id, Scope $scope, Role $role, array $permissionSlugs): array
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
            'permissions' => array_map(
                fn (string $slug): array => $this->permissionPayload($slug),
                $permissionSlugs,
            ),
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
}
