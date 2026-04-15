<?php

namespace Tests\Feature\Api;

use App\Models\Checklist;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChecklistShowControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_checklist_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-show', 'tenant_viewer');

        $checklist = Checklist::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-checklist',
            'name' => 'Tenant Checklist',
        ]);

        $response = $this->withAccessToken('keycloak-user-checklist-show')
            ->getJson('/api/checklists/'.$checklist->id);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $checklist->id,
                    'scope_id' => $scope->id,
                    'code' => 'tenant-checklist',
                    'name' => 'Tenant Checklist',
                ],
            ]);
    }
}
