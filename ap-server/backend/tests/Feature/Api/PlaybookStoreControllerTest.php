<?php

namespace Tests\Feature\Api;

use App\Models\Playbook;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlaybookStoreControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_playbook_when_the_target_scope_is_accessible(): void
    {
        $serverScope = $this->assignRole('keycloak-user-playbook-store', 'server_admin');
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $response = $this->withAccessToken('keycloak-user-playbook-store')
            ->postJson('/api/playbooks', $this->playbookPayload(
                $tenantScope->id,
                ' Tenant_Playbook ',
                'Tenant Playbook',
            ));

        $this->assertPlaybookResponse($response, $tenantScope->id, 'tenant-playbook', 'Tenant Playbook');
    }

    public function test_it_returns_validation_errors_when_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-playbook-store-dup', 'tenant_admin');

        Playbook::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-playbook',
            'name' => 'Existing Playbook',
        ]);

        $response = $this->withAccessToken('keycloak-user-playbook-store-dup')
            ->postJson('/api/playbooks', $this->playbookPayload(
                $scope->id,
                ' Tenant_Playbook ',
                'Duplicated Playbook',
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

    private function assertPlaybookResponse($response, int $scopeId, string $code, string $name): void
    {
        $playbookId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.id', $playbookId)
            ->assertJsonPath('data.scope_id', $scopeId)
            ->assertJsonPath('data.code', $code)
            ->assertJsonPath('data.name', $name);
    }

    /**
     * @return array{scope_id: int, code: string, name: string}
     */
    private function playbookPayload(int $scopeId, string $code, string $name): array
    {
        return [
            'scope_id' => $scopeId,
            'code' => $code,
            'name' => $name,
        ];
    }
}
