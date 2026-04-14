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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-checklists'))
            ->getJson('/api/checklists');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $serverChecklist->id,
                        'scope_id' => $serverScope->id,
                        'code' => 'server-checklist',
                        'name' => 'Server Checklist',
                    ],
                    [
                        'id' => $tenantChecklist->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'tenant-checklist',
                        'name' => 'Tenant Checklist',
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
        $this->assertIndexRejectsInvalidFilters('/api/checklists');
    }
}
