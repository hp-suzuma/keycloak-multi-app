<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Playbook;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaybookIndexControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_requires_the_object_read_permission(): void
    {
        $response = $this->getJson('/api/playbooks');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.read'],
            ]);
    }

    public function test_it_returns_only_playbooks_in_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-playbooks', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $otherScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-b',
            'name' => 'Server B',
        ]);

        $serverPlaybook = Playbook::query()->create([
            'scope_id' => $serverScope->id,
            'code' => 'server-runbook',
            'name' => 'Server Runbook',
        ]);

        $tenantPlaybook = Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-runbook',
            'name' => 'Tenant Runbook',
        ]);

        Playbook::query()->create([
            'scope_id' => $otherScope->id,
            'code' => 'forbidden-runbook',
            'name' => 'Forbidden Runbook',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbooks'))
            ->getJson('/api/playbooks');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $serverPlaybook->id,
                        'scope_id' => $serverScope->id,
                        'code' => 'server-runbook',
                        'name' => 'Server Runbook',
                    ],
                    [
                        'id' => $tenantPlaybook->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'tenant-runbook',
                        'name' => 'Tenant Runbook',
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

    public function test_it_applies_filter_and_sort_within_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-playbooks-filter', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $firstPlaybook = Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'alpha-playbook',
            'name' => 'Target Alpha',
        ]);

        $secondPlaybook = Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'zebra-playbook',
            'name' => 'Target Zebra',
        ]);

        Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'ignored-playbook',
            'name' => 'Ignored Name',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbooks-filter'))
            ->getJson('/api/playbooks?scope_id='.$tenantScope->id.'&name=Target&sort=code');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $firstPlaybook->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'alpha-playbook',
                        'name' => 'Target Alpha',
                    ],
                    [
                        'id' => $secondPlaybook->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'zebra-playbook',
                        'name' => 'Target Zebra',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 2,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => $tenantScope->id,
                        'code' => null,
                        'name' => 'Target',
                        'sort' => 'code',
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

        $scope ??= $this->createDefaultScopeForRole($keycloakSub, $roleSlug);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);

        return $scope;
    }
}
