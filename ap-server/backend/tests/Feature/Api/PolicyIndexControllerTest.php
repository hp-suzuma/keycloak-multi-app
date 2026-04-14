<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Policy;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PolicyIndexControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_policies_in_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-policies', 'server_admin');
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $serverPolicy = Policy::query()->create([
            'scope_id' => $serverScope->id,
            'code' => 'server-policy',
            'name' => 'Server Policy',
        ]);

        $tenantPolicy = Policy::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-policy',
            'name' => 'Tenant Policy',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-policies'))
            ->getJson('/api/policies');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $serverPolicy->id,
                        'scope_id' => $serverScope->id,
                        'code' => 'server-policy',
                        'name' => 'Server Policy',
                    ],
                    [
                        'id' => $tenantPolicy->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'tenant-policy',
                        'name' => 'Tenant Policy',
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

        $scope ??= $this->createDefaultScopeForRole($keycloakSub, $roleSlug);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);

        return $scope;
    }
}
