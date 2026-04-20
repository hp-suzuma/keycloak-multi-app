<?php

namespace Tests\Feature\Api;

use App\Models\Checklist;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChecklistStoreControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_checklist_when_the_target_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-store', 'tenant_admin');

        $response = $this->withAccessToken('keycloak-user-checklist-store')
            ->postJson('/api/checklists', $this->checklistPayload(
                $scope->id,
                ' Tenant_Checklist ',
                'Tenant Checklist',
            ));

        $this->assertChecklistResponse($response, $scope->id, 'tenant-checklist', 'Tenant Checklist');

        $this->assertDatabaseHas('checklists', [
            'id' => $response->json('data.id'),
            'scope_id' => $scope->id,
            'code' => 'tenant-checklist',
        ]);
    }

    public function test_it_returns_validation_errors_when_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-store-dup', 'tenant_admin');

        Checklist::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-checklist',
            'name' => 'Existing Checklist',
        ]);

        $response = $this->withAccessToken('keycloak-user-checklist-store-dup')
            ->postJson('/api/checklists', $this->checklistPayload(
                $scope->id,
                ' Tenant_Checklist ',
                'Duplicated Checklist',
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

    private function assertChecklistResponse($response, int $scopeId, string $code, string $name): void
    {
        $checklistId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.id', $checklistId)
            ->assertJsonPath('data.scope_id', $scopeId)
            ->assertJsonPath('data.code', $code)
            ->assertJsonPath('data.name', $name);
    }

    /**
     * @return array{scope_id: int, code: string, name: string}
     */
    private function checklistPayload(int $scopeId, string $code, string $name): array
    {
        return [
            'scope_id' => $scopeId,
            'code' => $code,
            'name' => $name,
        ];
    }
}
