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
                    $this->policyPayload($serverPolicy->id, $serverScope->id, 'server-policy', 'Server Policy'),
                    $this->policyPayload($tenantPolicy->id, $tenantScope->id, 'tenant-policy', 'Tenant Policy'),
                ],
                'meta' => $this->metaPayload(2),
            ]);
    }

    public function test_it_rejects_invalid_query_filters(): void
    {
        $this->assertIndexRejectsInvalidFilters('/api/policies');
    }

    private function policyPayload(int $id, int $scopeId, string $code, string $name): array
    {
        return [
            'id' => $id,
            'scope_id' => $scopeId,
            'code' => $code,
            'name' => $name,
        ];
    }

    private function metaPayload(int $total, array $filters = []): array
    {
        return [
            'current_page' => 1,
            'per_page' => 20,
            'total' => $total,
            'last_page' => 1,
            'filters' => array_merge([
                'scope_id' => null,
                'code' => null,
                'name' => null,
                'sort' => null,
            ], $filters),
        ];
    }
}
