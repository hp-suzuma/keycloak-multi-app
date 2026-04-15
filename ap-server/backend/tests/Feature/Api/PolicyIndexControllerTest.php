<?php

namespace Tests\Feature\Api;

use App\Models\Policy;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyIndexControllerTest extends ScopedIndexValidationApiTestCase
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

        $response = $this->withAccessToken('keycloak-user-policies')
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

    public function test_it_rejects_invalid_query_filters(): void
    {
        $this->assertIndexRejectsInvalidFilters('/api/policies');
    }
}
