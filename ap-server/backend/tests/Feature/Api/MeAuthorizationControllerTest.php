<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MeAuthorizationControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_authorization_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/me/authorization');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => null,
                'authorization' => null,
            ]);
    }

    public function test_it_returns_assignments_and_permissions_for_a_keycloak_user_managed_in_the_ap_database(): void
    {
        ApUser::query()->create([
            'keycloak_sub' => 'keycloak-user-1',
            'display_name' => 'AP User',
            'email' => 'ap-user@example.com',
        ]);

        $serverScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-authz-1',
            'name' => 'Server 1',
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-authz-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $serverAdminRole = Role::query()->where('slug', 'server_admin')->firstOrFail();
        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        UserRoleAssignment::query()->create([
            'keycloak_sub' => 'keycloak-user-1',
            'role_id' => $serverAdminRole->id,
            'scope_id' => $serverScope->id,
        ]);

        UserRoleAssignment::query()->create([
            'keycloak_sub' => 'keycloak-user-1',
            'role_id' => $tenantViewerRole->id,
            'scope_id' => $tenantScope->id,
        ]);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-1',
            'aud' => ['ap-frontend'],
            'preferred_username' => 'kc-user',
            'email' => 'kc-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me/authorization');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-1', 'kc-user', 'kc-user@example.com'),
                'authorization' => [
                    'keycloak_sub' => 'keycloak-user-1',
                    'assignments' => [
                        [
                            'scope' => $this->scopePayload($serverScope->id, 'server', 'srv-authz-1', 'Server 1'),
                            'role' => $this->rolePayload($serverAdminRole->id, 'server_admin', 'Server Admin', 'server', 'admin'),
                            'permissions' => $this->permissionsPayload(
                                'object.read',
                                'object.update',
                                'object.create',
                                'object.delete',
                                'object.execute',
                            ),
                        ],
                        [
                            'scope' => $this->scopePayload($tenantScope->id, 'tenant', 'tenant-authz-a', 'Tenant A', $serverScope->id),
                            'role' => $this->rolePayload($tenantViewerRole->id, 'tenant_viewer', 'Tenant Viewer', 'tenant', 'viewer'),
                            'permissions' => $this->permissionsPayload('object.read'),
                        ],
                    ],
                    'permissions' => [
                        'object.create',
                        'object.delete',
                        'object.execute',
                        'object.read',
                        'object.update',
                    ],
                    'permission_scopes' => [
                        'object.create' => [
                            'granted_scope_ids' => [$serverScope->id],
                            'accessible_scope_ids' => [$serverScope->id, $tenantScope->id],
                        ],
                        'object.delete' => [
                            'granted_scope_ids' => [$serverScope->id],
                            'accessible_scope_ids' => [$serverScope->id, $tenantScope->id],
                        ],
                        'object.execute' => [
                            'granted_scope_ids' => [$serverScope->id],
                            'accessible_scope_ids' => [$serverScope->id, $tenantScope->id],
                        ],
                        'object.read' => [
                            'granted_scope_ids' => [$serverScope->id, $tenantScope->id],
                            'accessible_scope_ids' => [$serverScope->id, $tenantScope->id],
                        ],
                        'object.update' => [
                            'granted_scope_ids' => [$serverScope->id],
                            'accessible_scope_ids' => [$serverScope->id, $tenantScope->id],
                        ],
                    ],
                ],
            ]);
    }

    public function test_it_returns_an_empty_assignment_list_when_the_keycloak_user_has_no_ap_record_yet(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-2',
            'aud' => ['ap-frontend'],
            'preferred_username' => 'kc-user-2',
            'email' => 'kc-user-2@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me/authorization');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-2', 'kc-user-2', 'kc-user-2@example.com'),
                'authorization' => [
                    'keycloak_sub' => 'keycloak-user-2',
                    'assignments' => [],
                    'permissions' => [],
                    'permission_scopes' => [],
                ],
            ]);
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

    /**
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    private function permissionsPayload(string ...$slugs): array
    {
        return array_map(
            fn (string $slug): array => $this->permissionPayload($slug),
            $slugs,
        );
    }

    private function scopePayload(
        int $id,
        string $layer,
        string $code,
        string $name,
        ?int $parentScopeId = null,
    ): array {
        return [
            'id' => $id,
            'layer' => $layer,
            'code' => $code,
            'name' => $name,
            'parent_scope_id' => $parentScopeId,
        ];
    }

    private function rolePayload(
        int $id,
        string $slug,
        string $name,
        string $scopeLayer,
        string $permissionRole,
    ): array {
        return [
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'scope_layer' => $scopeLayer,
            'permission_role' => $permissionRole,
        ];
    }

    private function currentUserPayload(string $id, string $name, string $email): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];
    }
}
