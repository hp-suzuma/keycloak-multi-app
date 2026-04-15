<?php

namespace App\Services\Role;

use App\Models\Role;

class RolePayload
{
    /**
     * @return array{
     *     id: int,
     *     slug: string,
     *     name: string,
     *     scope_layer: string,
     *     permission_role: string,
     *     permissions: array<int, array{id: int, slug: string, name: string}>
     * }
     */
    public static function fromModel(Role $role): array
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
                ->map(fn ($permission): array => [
                    'id' => $permission->id,
                    'slug' => $permission->slug,
                    'name' => $permission->name,
                ])
                ->all(),
        ];
    }
}
