<?php

namespace Tests\Feature\Api;

use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScopeIndexControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_requires_the_user_manage_permission(): void
    {
        $this->assignRole('keycloak-scope-index-without-manage', 'tenant_viewer');

        $response = $this->withAccessToken('keycloak-scope-index-without-manage')
            ->getJson('/api/scopes');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['user.manage'],
            ]);
    }

    public function test_it_returns_only_manageable_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-scope-index', 'server_user_manager');

        $serviceScope = Scope::query()->create([
            'layer' => 'service',
            'code' => 'svc-a',
            'name' => 'Service A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serviceScope->id,
        ]);

        Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-hidden',
            'name' => 'Server Hidden',
        ]);

        $response = $this->withAccessToken('keycloak-scope-index')
            ->getJson('/api/scopes');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->scopePayload($serverScope->id, 'server', 'server_user_manager-scope-keycloak-scope-index', 'server_user_manager scope'),
                    $this->scopePayload($serviceScope->id, 'service', 'svc-a', 'Service A', $serverScope->id),
                    $this->scopePayload($tenantScope->id, 'tenant', 'tenant-a', 'Tenant A', $serviceScope->id),
                ],
            ]);
    }

    public function test_it_applies_layer_parent_code_name_and_sort_filters(): void
    {
        $serverScope = $this->assignRole('keycloak-scope-index-filters', 'server_user_manager');

        $serviceScope = Scope::query()->create([
            'layer' => 'service',
            'code' => 'svc-a',
            'name' => 'Service Alpha',
            'parent_scope_id' => $serverScope->id,
        ]);

        Scope::query()->create([
            'layer' => 'service',
            'code' => 'svc-b',
            'name' => 'Service Beta',
            'parent_scope_id' => $serverScope->id,
        ]);

        $response = $this->withAccessToken('keycloak-scope-index-filters')
            ->getJson('/api/scopes?layer=service&parent_scope_id='.$serverScope->id.'&code=svc-a&name=Alpha&sort=-name');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->scopePayload($serviceScope->id, 'service', 'svc-a', 'Service Alpha', $serverScope->id),
                ],
            ]);
    }

    public function test_it_rejects_invalid_query_filters(): void
    {
        $this->assignRole('keycloak-scope-index-invalid', 'server_user_manager');

        $response = $this->withAccessToken('keycloak-scope-index-invalid')
            ->getJson('/api/scopes?layer=invalid&parent_scope_id=invalid&sort=invalid');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['layer', 'parent_scope_id', 'sort']);
    }

    private function scopePayload(
        int $id,
        string $layer,
        string $code,
        string $name,
        ?int $parentScopeId = null,
    ): array {
        return [
            'id' => $id,
            'layer' => $layer,
            'code' => $code,
            'name' => $name,
            'parent_scope_id' => $parentScopeId,
        ];
    }
}
