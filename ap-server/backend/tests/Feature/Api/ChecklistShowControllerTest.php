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

class ChecklistShowControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_checklist_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-show', 'tenant_viewer');

        $checklist = Checklist::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-checklist',
            'name' => 'Tenant Checklist',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-checklist-show'))
            ->getJson('/api/checklists/'.$checklist->id);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $checklist->id,
                    'scope_id' => $scope->id,
                    'code' => 'tenant-checklist',
                    'name' => 'Tenant Checklist',
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
