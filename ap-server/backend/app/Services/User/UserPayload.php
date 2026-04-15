<?php

namespace App\Services\User;

use App\Models\ApUser;

class UserPayload
{
    /**
     * @return array{
     *     keycloak_sub: string,
     *     display_name: string|null,
     *     email: string|null,
     *     assignments: array<int, array{
     *         id: int,
     *         scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *         role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *         permissions: array<int, array{id: int, slug: string, name: string}>
     *     }>,
     *     permissions: array<int, string>
     * }
     */
    public static function fromModel(ApUser $user): array
    {
        $assignments = $user->roleAssignments
            ->sortBy('id')
            ->values()
            ->map(function ($assignment): array {
                $scope = $assignment->scope;
                $role = $assignment->role;
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
                    'id' => $assignment->id,
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
            ->all();

        $permissionSlugs = collect($assignments)
            ->flatMap(fn (array $assignment): array => array_column($assignment['permissions'], 'slug'))
            ->unique()
            ->values()
            ->all();

        return [
            'keycloak_sub' => $user->keycloak_sub,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'assignments' => $assignments,
            'permissions' => $permissionSlugs,
        ];
    }
}
