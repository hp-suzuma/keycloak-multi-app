<?php

namespace Tests\Feature\Api;

use App\Models\Checklist;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChecklistIndexControllerTest extends ScopedIndexValidationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_checklists_in_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-checklists', 'server_admin');
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-checklist-a',
            'name' => 'Tenant Checklist A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $serverChecklist = Checklist::query()->create([
            'scope_id' => $serverScope->id,
            'code' => 'server-checklist',
            'name' => 'Server Checklist',
        ]);

        $tenantChecklist = Checklist::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-checklist',
            'name' => 'Tenant Checklist',
        ]);

        $response = $this->withAccessToken('keycloak-user-checklists')
            ->getJson('/api/checklists');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    $this->checklistPayload($serverChecklist->id, $serverScope->id, 'server-checklist', 'Server Checklist'),
                    $this->checklistPayload($tenantChecklist->id, $tenantScope->id, 'tenant-checklist', 'Tenant Checklist'),
                ],
                'meta' => $this->metaPayload(2),
            ]);
    }

    public function test_it_rejects_invalid_query_filters(): void
    {
        $this->assertIndexRejectsInvalidFilters('/api/checklists');
    }

    private function checklistPayload(int $id, int $scopeId, string $code, string $name): array
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
