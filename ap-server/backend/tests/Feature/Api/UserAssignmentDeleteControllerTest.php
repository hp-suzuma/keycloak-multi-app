<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAssignmentDeleteControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_not_found_when_the_user_does_not_exist(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-assignment-delete-missing-user', 'tenant_user_manager');
        $role = Role::query()->where('slug', 'tenant_user_manager')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-assignment-delete-missing-user')
            ->deleteJson('/api/users/missing-user/assignments', [
                'scope_id' => $scope->id,
                'role_id' => $role->id,
            ]);

        $this->assertNotFoundResponse($response);
    }

    public function test_it_returns_not_found_when_the_assignment_is_not_visible(): void
    {
        $this->assignRole('keycloak-user-manager-assignment-delete-hidden', 'tenant_user_manager');

        $hiddenScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-hidden',
            'name' => 'Tenant Hidden',
        ]);

        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $hiddenRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();
        $this->assignManagedRole($user, 'tenant_viewer', $hiddenScope);

        $response = $this->withAccessToken('keycloak-user-manager-assignment-delete-hidden')
            ->deleteJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $hiddenScope->id,
                'role_id' => $hiddenRole->id,
            ]);

        $this->assertNotFoundResponse($response);
    }

    public function test_it_deletes_a_visible_assignment(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-assignment-delete', 'tenant_user_manager');
        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $role = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $this->assignManagedRole($user, 'tenant_viewer', $scope);

        $response = $this->withAccessToken('keycloak-user-manager-assignment-delete')
            ->deleteJson('/api/users/'.$user->keycloak_sub.'/assignments', [
                'scope_id' => $scope->id,
                'role_id' => $role->id,
            ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('user_role_assignments', [
            'keycloak_sub' => $user->keycloak_sub,
            'scope_id' => $scope->id,
            'role_id' => $role->id,
            'is_deleted' => true,
        ]);
    }

    public function test_it_requires_the_user_manage_permission(): void
    {
        $scope = $this->assignRole('keycloak-user-assignment-delete-without-manage', 'tenant_viewer');
        $user = $this->createManagedUser('managed-user', 'Managed User', 'managed@example.com');
        $role = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $this->assignManagedRole($user, 'tenant_viewer', $scope);

        $response = $this->withAccessToken('keycloak-user-assignment-delete-without-manage')
            ->deleteJson('/api/users/'.$user->keycloak_sub.'/assignments', [
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

    public function test_it_returns_not_found_for_assignment_id_when_the_user_does_not_exist(): void
    {
        $this->assignRole('keycloak-user-manager-assignment-item-delete-missing-user', 'tenant_user_manager');

        $response = $this->withAccessToken('keycloak-user-manager-assignment-item-delete-missing-user')
            ->deleteJson('/api/users/missing-user/assignments/999999');

        $this->assertNotFoundResponse($response);
    }

    public function test_it_returns_not_found_for_assignment_id_when_the_assignment_is_not_visible(): void
    {
        $this->assignRole('keycloak-user-manager-assignment-item-delete-hidden', 'tenant_user_manager');

        $hiddenScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-hidden-item',
            'name' => 'Tenant Hidden Item',
        ]);

        $user = $this->createManagedUser('managed-user-item-hidden', 'Managed User', 'managed-item-hidden@example.com');
        $assignment = $this->assignManagedRole($user, 'tenant_viewer', $hiddenScope);

        $response = $this->withAccessToken('keycloak-user-manager-assignment-item-delete-hidden')
            ->deleteJson('/api/users/'.$user->keycloak_sub.'/assignments/'.$assignment->id);

        $this->assertNotFoundResponse($response);
    }

    public function test_it_deletes_a_visible_assignment_by_assignment_id(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-assignment-item-delete', 'tenant_user_manager');
        $user = $this->createManagedUser('managed-user-item', 'Managed User', 'managed-item@example.com');
        $assignment = $this->assignManagedRole($user, 'tenant_viewer', $scope);

        $response = $this->withAccessToken('keycloak-user-manager-assignment-item-delete')
            ->deleteJson('/api/users/'.$user->keycloak_sub.'/assignments/'.$assignment->id);

        $response->assertNoContent();

        $this->assertDatabaseHas('user_role_assignments', [
            'id' => $assignment->id,
            'is_deleted' => true,
        ]);
    }

    public function test_it_requires_the_user_manage_permission_for_assignment_id_delete(): void
    {
        $scope = $this->assignRole('keycloak-user-assignment-item-delete-without-manage', 'tenant_viewer');
        $user = $this->createManagedUser('managed-user-item-no-manage', 'Managed User', 'managed-item-no-manage@example.com');
        $assignment = $this->assignManagedRole($user, 'tenant_viewer', $scope);

        $response = $this->withAccessToken('keycloak-user-assignment-item-delete-without-manage')
            ->deleteJson('/api/users/'.$user->keycloak_sub.'/assignments/'.$assignment->id);

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

    private function assignManagedRole(ApUser $user, string $roleSlug, Scope $scope): UserRoleAssignment
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return UserRoleAssignment::query()->create([
            'keycloak_sub' => $user->keycloak_sub,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
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
