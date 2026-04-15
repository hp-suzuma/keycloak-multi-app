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
            'code' => 'srv-1',
            'name' => 'Server 1',
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
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
                            'scope' => [
                                'id' => $serverScope->id,
                                'layer' => 'server',
                                'code' => 'srv-1',
                                'name' => 'Server 1',
                                'parent_scope_id' => null,
                            ],
                            'role' => [
                                'id' => $serverAdminRole->id,
                                'slug' => 'server_admin',
                                'name' => 'Server Admin',
                                'scope_layer' => 'server',
                                'permission_role' => 'admin',
                            ],
                            'permissions' => [
                                $this->permissionPayload('object.read'),
                                $this->permissionPayload('object.update'),
                                $this->permissionPayload('object.create'),
                                $this->permissionPayload('object.delete'),
                                $this->permissionPayload('object.execute'),
                            ],
                        ],
                        [
                            'scope' => [
                                'id' => $tenantScope->id,
                                'layer' => 'tenant',
                                'code' => 'tenant-a',
                                'name' => 'Tenant A',
                                'parent_scope_id' => $serverScope->id,
                            ],
                            'role' => [
                                'id' => $tenantViewerRole->id,
                                'slug' => 'tenant_viewer',
                                'name' => 'Tenant Viewer',
                                'scope_layer' => 'tenant',
                                'permission_role' => 'viewer',
                            ],
                            'permissions' => [
                                $this->permissionPayload('object.read'),
                            ],
                        ],
                    ],
                    'permissions' => [
                        'object.read',
                        'object.update',
                        'object.create',
                        'object.delete',
                        'object.execute',
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

    private function currentUserPayload(string $id, string $name, string $email): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];
    }
}
