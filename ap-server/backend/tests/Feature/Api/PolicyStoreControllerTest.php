<?php

namespace Tests\Feature\Api;

use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyStoreControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_policy_when_the_target_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-store', 'tenant_admin');

        $response = $this->withAccessToken('keycloak-user-policy-store')
            ->postJson('/api/policies', $this->policyPayload(
                $scope->id,
                ' Tenant_Policy ',
                'Tenant Policy',
            ));

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => 1,
                    'scope_id' => $scope->id,
                    'code' => 'tenant-policy',
                    'name' => 'Tenant Policy',
                ],
            ]);

        $this->assertDatabaseHas('policies', [
            'scope_id' => $scope->id,
            'code' => 'tenant-policy',
        ]);
    }

    public function test_it_returns_validation_errors_when_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-store-dup', 'tenant_admin');

        Policy::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-policy',
            'name' => 'Existing Policy',
        ]);

        $response = $this->withAccessToken('keycloak-user-policy-store-dup')
            ->postJson('/api/policies', $this->policyPayload(
                $scope->id,
                ' Tenant_Policy ',
                'Duplicated Policy',
            ));

        $this->assertDuplicateCodeValidationResponse($response);
    }

    private function assertDuplicateCodeValidationResponse($response): void
    {
        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'errors' => [
                    'code' => ['The code has already been taken within the target scope.'],
                ],
            ]);
    }

    /**
     * @return array{scope_id: int, code: string, name: string}
     */
    private function policyPayload(int $scopeId, string $code, string $name): array
    {
        return [
            'scope_id' => $scopeId,
            'code' => $code,
            'name' => $name,
        ];
    }
}
