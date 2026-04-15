<?php

namespace Tests\Feature\Api;

use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyShowControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_policy_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-show', 'tenant_viewer');

        $policy = Policy::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-policy',
            'name' => 'Tenant Policy',
        ]);

        $response = $this->withAccessToken('keycloak-user-policy-show')
            ->getJson('/api/policies/'.$policy->id);

        $this->assertPolicyResponse($response, $policy->id, $scope->id, 'tenant-policy', 'Tenant Policy');
    }

    private function assertPolicyResponse($response, int $policyId, int $scopeId, string $code, string $name): void
    {
        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $policyId,
                    'scope_id' => $scopeId,
                    'code' => $code,
                    'name' => $name,
                ],
            ]);
    }
}
