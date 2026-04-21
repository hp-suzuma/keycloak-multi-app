<?php

namespace App\Services\Authorization;

use App\Models\ApUser;
use App\Models\Scope;
use App\Services\Auth\CurrentUser;

class AuthorizationService
{
    /**
     * @return array{
     *     current_user: array{id: int|string, name: string, email: string}|null,
     *     authorization: array{
     *         keycloak_sub: string,
     *         assignments: array<int, array{
     *             scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *             role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *             permissions: array<int, array{id: int, slug: string, name: string}>
     *         }>,
     *         permissions: array<int, string>,
     *         permission_scopes: array<string, array{
     *             granted_scope_ids: array<int, int>,
     *             accessible_scope_ids: array<int, int>
     *         }>
     *     }|null
     * }
     */
    public function buildResponse(?CurrentUser $currentUser): array
    {
        if ($currentUser === null) {
            return [
                'current_user' => null,
                'authorization' => null,
            ];
        }

        $authorization = $this->resolveAuthorization($currentUser);

        if ($authorization === null) {
            return [
                'current_user' => $currentUser->toArray(),
                'authorization' => null,
            ];
        }

        return [
            'current_user' => $currentUser->toArray(),
            'authorization' => $authorization,
        ];
    }

    /**
     * @param  array<int, string>  $requiredPermissions
     */
    public function hasRequiredPermissions(?CurrentUser $currentUser, array $requiredPermissions): bool
    {
        if ($requiredPermissions === []) {
            return true;
        }

        $authorization = $this->resolveAuthorization($currentUser);

        if ($authorization === null) {
            return false;
        }

        $effectivePermissions = collect($authorization['permissions']);

        return collect($requiredPermissions)
            ->every(fn (string $permission): bool => $effectivePermissions->contains($permission));
    }

    public function canAccessScope(?CurrentUser $currentUser, string $requiredPermission, int $scopeId): bool
    {
        return collect($this->accessibleScopeIds($currentUser, [$requiredPermission]))
            ->contains($scopeId);
    }

    /**
     * @param  array<int, string>  $requiredPermissions
     * @return array<int, int>
     */
    public function grantedScopeIds(?CurrentUser $currentUser, array $requiredPermissions): array
    {
        if ($requiredPermissions === []) {
            return [];
        }

        $authorization = $this->resolveAuthorization($currentUser);

        if ($authorization === null) {
            return [];
        }

        return $this->grantedScopeIdsFromAssignments($authorization['assignments'], $requiredPermissions);
    }

    /**
     * @param  array<int, string>  $requiredPermissions
     * @return array<int, int>
     */
    public function accessibleScopeIds(?CurrentUser $currentUser, array $requiredPermissions): array
    {
        $assignmentScopeIds = collect($this->grantedScopeIds($currentUser, $requiredPermissions));

        if ($assignmentScopeIds->isEmpty()) {
            return [];
        }

        return $this->expandScopeIdsWithDescendants($assignmentScopeIds->all());
    }

    /**
     * @return array{
     *     keycloak_sub: string,
     *     assignments: array<int, array{
     *         scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *         role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *         permissions: array<int, array{id: int, slug: string, name: string}>
     *     }>,
     *     permissions: array<int, string>,
     *     permission_scopes: array<string, array{
     *         granted_scope_ids: array<int, int>,
     *         accessible_scope_ids: array<int, int>
     *     }>
     * }|null
     */
    private function resolveAuthorization(?CurrentUser $currentUser): ?array
    {
        if ($currentUser === null || ! is_string($currentUser->id)) {
            return null;
        }

        $apUser = ApUser::query()
            ->with([
                'roleAssignments.scope',
                'roleAssignments.role.permissions',
            ])
            ->find($currentUser->id);

        $assignments = $apUser?->roleAssignments
            ->sortBy('id')
            ->values()
            ->map(function ($assignment): array {
                $role = $assignment->role;
                $scope = $assignment->scope;
                $permissions = $role->permissions
                    ->sortBy('id')
                    ->values()
                    ->map(fn ($permission): array => [
                        'id' => $permission->id,
                        'slug' => $permission->slug,
                        'name' => $permission->name,
                    ])
                    ->all();

                return [
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
                    'permissions' => $permissions,
                ];
            })
            ->all() ?? [];

        $effectivePermissions = collect($assignments)
            ->flatMap(fn (array $assignment): array => array_column($assignment['permissions'], 'slug'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $permissionScopes = collect($effectivePermissions)
            ->mapWithKeys(fn (string $permission): array => [
                $permission => [
                    'granted_scope_ids' => $this->grantedScopeIdsFromAssignments($assignments, [$permission]),
                    'accessible_scope_ids' => $this->expandScopeIdsWithDescendants(
                        $this->grantedScopeIdsFromAssignments($assignments, [$permission]),
                    ),
                ],
            ])
            ->all();

        return [
            'keycloak_sub' => $currentUser->id,
            'assignments' => $assignments,
            'permissions' => $effectivePermissions,
            'permission_scopes' => $permissionScopes,
        ];
    }

    /**
     * @param  array<int, array{
     *     scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *     role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *     permissions: array<int, array{id: int, slug: string, name: string}>
     * }>  $assignments
     * @param  array<int, string>  $requiredPermissions
     * @return array<int, int>
     */
    private function grantedScopeIdsFromAssignments(array $assignments, array $requiredPermissions): array
    {
        return collect($assignments)
            ->filter(function (array $assignment) use ($requiredPermissions): bool {
                $permissionSlugs = collect($assignment['permissions'])
                    ->pluck('slug');

                return collect($requiredPermissions)
                    ->every(fn (string $permission): bool => $permissionSlugs->contains($permission));
            })
            ->pluck('scope.id')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $scopeIds
     * @return array<int, int>
     */
    private function expandScopeIdsWithDescendants(array $scopeIds): array
    {
        if ($scopeIds === []) {
            return [];
        }

        $descendantsByParent = Scope::query()
            ->get(['id', 'parent_scope_id'])
            ->groupBy('parent_scope_id');

        $accessibleScopeIds = collect();
        $queue = $scopeIds;

        while ($queue !== []) {
            $currentScopeId = array_shift($queue);

            if ($currentScopeId === null || $accessibleScopeIds->contains($currentScopeId)) {
                continue;
            }

            $accessibleScopeIds->push($currentScopeId);

            foreach ($descendantsByParent->get($currentScopeId, collect()) as $childScope) {
                $queue[] = $childScope->id;
            }
        }

        return $accessibleScopeIds
            ->sort()
            ->values()
            ->all();
    }
}
