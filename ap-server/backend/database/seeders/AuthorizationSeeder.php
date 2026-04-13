<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissionMap = [
            'user.manage' => 'User Manage',
            'object.read' => 'Object Read',
            'object.update' => 'Object Update',
            'object.create' => 'Object Create',
            'object.delete' => 'Object Delete',
            'object.execute' => 'Object Execute',
        ];

        foreach ($permissionMap as $slug => $name) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        $roleDefinitions = [
            'admin' => ['object.read', 'object.update', 'object.create', 'object.delete', 'object.execute'],
            'operator' => ['object.read', 'object.update', 'object.execute'],
            'viewer' => ['object.read'],
        ];

        foreach (['server', 'service', 'tenant'] as $scopeLayer) {
            foreach ($roleDefinitions as $permissionRole => $permissionSlugs) {
                $role = Role::query()->updateOrCreate(
                    ['slug' => $scopeLayer.'_'.$permissionRole],
                    [
                        'scope_layer' => $scopeLayer,
                        'permission_role' => $permissionRole,
                        'name' => str($scopeLayer)->replace('_', ' ')->title().' '.str($permissionRole)->title(),
                    ],
                );

                $role->permissions()->sync(
                    Permission::query()
                        ->whereIn('slug', $permissionSlugs)
                        ->pluck('id')
                        ->all(),
                );
            }
        }
    }
}
