<?php

namespace Database\Seeders;

use App\Models\ApUser;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;

class ApUserManagementDemoSeeder extends Seeder
{
    /**
     * Seed a minimal dataset for AP users live-mode verification.
     */
    public function run(): void
    {
        $serverScope = Scope::query()->updateOrCreate(
            ['layer' => 'server', 'code' => 'ap-root'],
            ['name' => 'AP Root', 'parent_scope_id' => null],
        );

        $serviceScope = Scope::query()->updateOrCreate(
            ['layer' => 'service', 'code' => 'svc-alpha'],
            ['name' => 'Service Alpha', 'parent_scope_id' => $serverScope->id],
        );

        $tenantScope = Scope::query()->updateOrCreate(
            ['layer' => 'tenant', 'code' => 'tenant-a'],
            ['name' => 'Tenant A', 'parent_scope_id' => $serviceScope->id],
        );

        ApUser::query()->updateOrCreate(
            ['keycloak_sub' => 'tenant-user-a'],
            ['display_name' => 'Alice A', 'email' => 'alice@example.com'],
        );

        ApUser::query()->updateOrCreate(
            ['keycloak_sub' => 'tenant-user-b'],
            ['display_name' => 'Bob B', 'email' => 'bob@example.com'],
        );

        $serverUserManagerRole = Role::query()->where('slug', 'server_user_manager')->firstOrFail();
        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        UserRoleAssignment::query()->updateOrCreate(
            [
                'keycloak_sub' => 'tenant-user-a',
                'role_id' => $serverUserManagerRole->id,
                'scope_id' => $serverScope->id,
            ],
            [],
        );

        UserRoleAssignment::query()->updateOrCreate(
            [
                'keycloak_sub' => 'tenant-user-b',
                'role_id' => $tenantViewerRole->id,
                'scope_id' => $tenantScope->id,
            ],
            [],
        );
    }
}
