<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserIndexControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_current_manager_when_no_other_managed_users_exist(): void
    {
        $scope = $this->assignRole('keycloak-user-manager-empty', 'tenant_user_manager');

        $tenantUserManagerRole = Role::query()->where('slug', 'tenant_user_manager')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-empty')
            ->getJson('/api/users');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->userPayload(
                        'keycloak-user-manager-empty',
                        'AP User',
                        'keycloak-user-manager-empty@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf('keycloak-user-manager-empty', $scope->id, $tenantUserManagerRole->id),
                                $scope,
                                $tenantUserManagerRole,
                                ['user.manage'],
                            ),
                        ],
                        ['user.manage'],
                    ),
                ],
                'meta' => $this->metaPayload(1),
            ]);
    }

    public function test_it_returns_only_users_with_assignments_in_manageable_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-manager', 'server_user_manager');

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

        $otherServerScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-b',
            'name' => 'Server B',
        ]);

        $visibleUser = $this->createManagedUser('managed-visible', 'Visible User', 'visible@example.com');
        $descendantUser = $this->createManagedUser('managed-descendant', 'Descendant User', 'descendant@example.com');
        $hiddenUser = $this->createManagedUser('managed-hidden', 'Hidden User', 'hidden@example.com');

        $this->assignManagedRole($visibleUser, 'server_admin', $serverScope);
        $this->assignManagedRole($descendantUser, 'tenant_viewer', $tenantScope);
        $this->assignManagedRole($hiddenUser, 'server_admin', $otherServerScope);

        $response = $this->withAccessToken('keycloak-user-manager')
            ->getJson('/api/users');

        $serverAdminRole = Role::query()->where('slug', 'server_admin')->firstOrFail();
        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->userPayload(
                        'managed-descendant',
                        'Descendant User',
                        'descendant@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf('managed-descendant', $tenantScope->id, $tenantViewerRole->id),
                                $tenantScope,
                                $tenantViewerRole,
                                ['object.read'],
                            ),
                        ],
                        ['object.read'],
                    ),
                    $this->userPayload(
                        'keycloak-user-manager',
                        'AP User',
                        'keycloak-user-manager@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf(
                                    'keycloak-user-manager',
                                    $serverScope->id,
                                    Role::query()->where('slug', 'server_user_manager')->firstOrFail()->id,
                                ),
                                $serverScope,
                                Role::query()->where('slug', 'server_user_manager')->firstOrFail(),
                                ['user.manage'],
                            ),
                        ],
                        ['user.manage'],
                    ),
                    $this->userPayload(
                        'managed-visible',
                        'Visible User',
                        'visible@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf('managed-visible', $serverScope->id, $serverAdminRole->id),
                                $serverScope,
                                $serverAdminRole,
                                [
                                'object.read',
                                'object.update',
                                'object.create',
                                'object.delete',
                                'object.execute',
                                ],
                            ),
                        ],
                        [
                            'object.read',
                            'object.update',
                            'object.create',
                            'object.delete',
                            'object.execute',
                        ],
                    ),
                ],
                'meta' => $this->metaPayload(3),
            ]);
    }

    public function test_it_applies_scope_and_keyword_filters_within_manageable_scopes(): void
    {
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
        ]);

        $otherScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-b',
            'name' => 'Tenant B',
        ]);

        $this->assignRole('keycloak-user-manager-filter', 'tenant_user_manager', $tenantScope);

        $targetUser = $this->createManagedUser('target-user', 'Alpha User', 'target-match@example.com');
        $otherVisibleUser = $this->createManagedUser('other-visible', 'Other Visible', 'other@example.com');
        $hiddenUser = $this->createManagedUser('hidden-user', 'Hidden User', 'hidden@example.com');

        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $this->assignManagedRole($targetUser, 'tenant_viewer', $tenantScope);
        $this->assignManagedRole($otherVisibleUser, 'tenant_viewer', $tenantScope);
        $this->assignManagedRole($hiddenUser, 'tenant_viewer', $otherScope);

        $response = $this->withAccessToken('keycloak-user-manager-filter')
            ->getJson('/api/users?scope_id='.$tenantScope->id.'&keyword=target-match');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->userPayload(
                        'target-user',
                        'Alpha User',
                        'target-match@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf('target-user', $tenantScope->id, $tenantViewerRole->id),
                                $tenantScope,
                                $tenantViewerRole,
                                ['object.read'],
                            ),
                        ],
                        ['object.read'],
                    ),
                ],
                'meta' => $this->metaPayload(1, [
                    'scope_id' => $tenantScope->id,
                    'keyword' => 'target-match',
                ]),
            ]);
    }

    public function test_a_service_manager_can_drill_down_to_a_tenant_user_list_with_scope_filter(): void
    {
        $serviceScope = $this->assignRole('keycloak-service-user-manager', 'service_user_manager');

        $tenantScopeA = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serviceScope->id,
        ]);

        $tenantScopeB = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'parent_scope_id' => $serviceScope->id,
        ]);

        $tenantAUser = $this->createManagedUser('tenant-a-user', 'Tenant A User', 'tenant-a@example.com');
        $tenantBUser = $this->createManagedUser('tenant-b-user', 'Tenant B User', 'tenant-b@example.com');

        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $this->assignManagedRole($tenantAUser, 'tenant_viewer', $tenantScopeA);
        $this->assignManagedRole($tenantBUser, 'tenant_viewer', $tenantScopeB);

        $response = $this->withAccessToken('keycloak-service-user-manager')
            ->getJson('/api/users?scope_id='.$tenantScopeA->id);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->userPayload(
                        'tenant-a-user',
                        'Tenant A User',
                        'tenant-a@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf('tenant-a-user', $tenantScopeA->id, $tenantViewerRole->id),
                                $tenantScopeA,
                                $tenantViewerRole,
                                ['object.read'],
                            ),
                        ],
                        ['object.read'],
                    ),
                ],
                'meta' => $this->metaPayload(1, [
                    'scope_id' => $tenantScopeA->id,
                ]),
            ]);
    }

    public function test_it_paginates_results_without_leaking_inaccessible_users(): void
    {
        $serverScope = $this->assignRole('keycloak-user-manager-page', 'server_user_manager');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $firstUser = $this->createManagedUser('managed-a', 'Managed A', 'managed-a@example.com');
        $secondUser = $this->createManagedUser('managed-b', 'Managed B', 'managed-b@example.com');
        $thirdUser = $this->createManagedUser('managed-c', 'Managed C', 'managed-c@example.com');

        $this->assignManagedRole($firstUser, 'server_admin', $serverScope);
        $this->assignManagedRole($secondUser, 'tenant_viewer', $tenantScope);
        $this->assignManagedRole($thirdUser, 'tenant_viewer', $tenantScope);

        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        $response = $this->withAccessToken('keycloak-user-manager-page')
            ->getJson('/api/users?page=2&per_page=2');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->userPayload(
                        'managed-b',
                        'Managed B',
                        'managed-b@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf('managed-b', $tenantScope->id, $tenantViewerRole->id),
                                $tenantScope,
                                $tenantViewerRole,
                                ['object.read'],
                            ),
                        ],
                        ['object.read'],
                    ),
                    $this->userPayload(
                        'managed-c',
                        'Managed C',
                        'managed-c@example.com',
                        [
                            $this->assignmentPayload(
                                $this->assignmentIdOf('managed-c', $tenantScope->id, $tenantViewerRole->id),
                                $tenantScope,
                                $tenantViewerRole,
                                ['object.read'],
                            ),
                        ],
                        ['object.read'],
                    ),
                ],
                'meta' => $this->metaPayload(4, currentPage: 2, perPage: 2, lastPage: 2),
            ]);
    }

    public function test_it_requires_the_user_manage_permission(): void
    {
        $this->assignRole('keycloak-user-without-manage', 'tenant_viewer');

        $response = $this->withAccessToken('keycloak-user-without-manage')
            ->getJson('/api/users');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['user.manage'],
            ]);
    }

    public function test_it_rejects_invalid_query_filters(): void
    {
        $this->assignRole('keycloak-user-manager-invalid', 'tenant_user_manager');

        $response = $this->withAccessToken('keycloak-user-manager-invalid')
            ->getJson('/api/users?sort=invalid&page=0&per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'page', 'per_page'])
            ->assertJsonPath('message', 'The selected sort is invalid. (and 2 more errors)');
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

    /**
     * @param  array<int, string>  $permissionSlugs
     * @return array{id: int, slug: string, name: string}
     */
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
     * @param  array<int, string>  $permissionSlugs
     * @return array{
     *     scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *     role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *     permissions: array<int, array{id: int, slug: string, name: string}>
     * }
     */
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

    /**
     * @param  array<int, array{
     *     id: int,
     *     scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *     role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *     permissions: array<int, array{id: int, slug: string, name: string}>
     * }>  $assignments
     * @param  array<int, string>  $permissions
     * @return array{
     *     keycloak_sub: string,
     *     display_name: string,
     *     email: string,
     *     assignments: array<int, array{
     *         scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *         role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *         permissions: array<int, array{id: int, slug: string, name: string}>
     *     }>,
     *     permissions: array<int, string>
     * }
     */
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

    /**
     * @param  array{scope_id?: int|null, keycloak_sub?: string|null, keyword?: string|null, sort?: string|null}  $filters
     * @return array{
     *     current_page: int,
     *     per_page: int,
     *     total: int,
     *     last_page: int,
     *     filters: array{scope_id: int|null, keycloak_sub: string|null, keyword: string|null, sort: string|null}
     * }
     */
    private function metaPayload(
        int $total,
        array $filters = [],
        int $currentPage = 1,
        int $perPage = 20,
        int $lastPage = 1,
    ): array {
        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'filters' => [
                'scope_id' => $filters['scope_id'] ?? null,
                'keycloak_sub' => $filters['keycloak_sub'] ?? null,
                'keyword' => $filters['keyword'] ?? null,
                'sort' => $filters['sort'] ?? null,
            ],
        ];
    }
}
