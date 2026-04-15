<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleIndexControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_requires_the_user_manage_permission(): void
    {
        $this->assignRole('keycloak-role-index-without-manage', 'tenant_viewer');

        $response = $this->withAccessToken('keycloak-role-index-without-manage')
            ->getJson('/api/roles');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['user.manage'],
            ]);
    }

    public function test_it_returns_roles_for_the_requested_scope_layer(): void
    {
        $this->assignRole('keycloak-role-index', 'server_user_manager');

        $tenantAdmin = Role::query()->where('slug', 'tenant_admin')->with('permissions')->firstOrFail();
        $tenantViewer = Role::query()->where('slug', 'tenant_viewer')->with('permissions')->firstOrFail();
        $tenantOperator = Role::query()->where('slug', 'tenant_operator')->with('permissions')->firstOrFail();
        $tenantUserManager = Role::query()->where('slug', 'tenant_user_manager')->with('permissions')->firstOrFail();

        $response = $this->withAccessToken('keycloak-role-index')
            ->getJson('/api/roles?scope_layer=tenant');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->rolePayload($tenantAdmin),
                    $this->rolePayload($tenantOperator),
                    $this->rolePayload($tenantUserManager),
                    $this->rolePayload($tenantViewer),
                ],
            ]);
    }

    public function test_it_applies_permission_role_slug_name_and_sort_filters(): void
    {
        $this->assignRole('keycloak-role-index-filters', 'server_user_manager');

        $response = $this->withAccessToken('keycloak-role-index-filters')
            ->getJson('/api/roles?scope_layer=tenant&permission_role=viewer&slug=viewer&name=Viewer&sort=-name');

        $tenantViewer = Role::query()->where('slug', 'tenant_viewer')->with('permissions')->firstOrFail();

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->rolePayload($tenantViewer),
                ],
            ]);
    }

    public function test_it_rejects_invalid_query_filters(): void
    {
        $this->assignRole('keycloak-role-index-invalid', 'server_user_manager');

        $response = $this->withAccessToken('keycloak-role-index-invalid')
            ->getJson('/api/roles?scope_layer=invalid&permission_role=invalid&sort=invalid');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope_layer', 'permission_role', 'sort']);
    }

    private function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
            'scope_layer' => $role->scope_layer,
            'permission_role' => $role->permission_role,
            'permissions' => $role->permissions
                ->sortBy('id')
                ->values()
                ->map(fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'slug' => $permission->slug,
                    'name' => $permission->name,
                ])
                ->all(),
        ];
    }
}
