<?php

namespace App\Services\Authorization;

use App\Models\ApUser;
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
     *         permissions: array<int, string>
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

        if (! is_string($currentUser->id)) {
            return [
                'current_user' => $currentUser->toArray(),
                'authorization' => null,
            ];
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
            ->values()
            ->all();

        return [
            'current_user' => $currentUser->toArray(),
            'authorization' => [
                'keycloak_sub' => $currentUser->id,
                'assignments' => $assignments,
                'permissions' => $effectivePermissions,
            ],
        ];
    }
}
