<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Checklist;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChecklistIndexControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_checklists_in_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-checklists', 'server_admin');
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-checklist-a',
            'name' => 'Tenant Checklist A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $serverChecklist = Checklist::query()->create([
            'scope_id' => $serverScope->id,
            'code' => 'server-checklist',
            'name' => 'Server Checklist',
        ]);

        $tenantChecklist = Checklist::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-checklist',
            'name' => 'Tenant Checklist',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-checklists'))
            ->getJson('/api/checklists');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $serverChecklist->id,
                        'scope_id' => $serverScope->id,
                        'code' => 'server-checklist',
                        'name' => 'Server Checklist',
                    ],
                    [
                        'id' => $tenantChecklist->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'tenant-checklist',
                        'name' => 'Tenant Checklist',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 2,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => null,
                        'code' => null,
                        'name' => null,
                        'sort' => null,
                    ],
                ],
            ]);
    }

    private function assignRole(string $keycloakSub, string $roleSlug, ?Scope $scope = null): Scope
    {
        ApUser::query()->updateOrCreate([
            'keycloak_sub' => $keycloakSub,
        ], [
            'display_name' => 'AP User',
            'email' => $keycloakSub.'@example.com',
        ]);

        $scope ??= Scope::query()->create([
            'layer' => str($roleSlug)->before('_')->value(),
            'code' => $roleSlug.'-scope-'.$keycloakSub,
            'name' => $roleSlug.' scope',
        ]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        UserRoleAssignment::query()->create([
            'keycloak_sub' => $keycloakSub,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
        ]);

        return $scope;
    }
}
