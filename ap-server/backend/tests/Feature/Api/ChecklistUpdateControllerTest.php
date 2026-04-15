<?php

namespace Tests\Feature\Api;

use App\Models\Checklist;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChecklistUpdateControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_updates_a_checklist_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-update', 'tenant_operator');

        $checklist = Checklist::query()->create([
            'scope_id' => $scope->id,
            'code' => 'checklist-a',
            'name' => 'Checklist A',
        ]);

        $response = $this->withAccessToken('keycloak-user-checklist-update')
            ->patchJson('/api/checklists/'.$checklist->id, [
                'code' => ' Updated_Checklist ',
                'name' => 'Updated Checklist',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $checklist->id,
                    'scope_id' => $scope->id,
                    'code' => 'updated-checklist',
                    'name' => 'Updated Checklist',
                ],
            ]);
    }

    public function test_it_moves_the_checklist_when_the_user_can_update_current_scope_and_create_in_target_scope(): void
    {
        $currentScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-checklist-current',
            'name' => 'Tenant Checklist Current',
        ]);
        $targetScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-checklist-target',
            'name' => 'Tenant Checklist Target',
        ]);

        $this->assignRole('keycloak-user-checklist-move', 'tenant_operator', $currentScope);
        $this->assignRole('keycloak-user-checklist-move', 'tenant_admin', $targetScope);

        $checklist = Checklist::query()->create([
            'scope_id' => $currentScope->id,
            'code' => 'checklist-a',
            'name' => 'Checklist A',
        ]);

        $response = $this->withAccessToken('keycloak-user-checklist-move')
            ->patchJson('/api/checklists/'.$checklist->id, [
                'scope_id' => $targetScope->id,
                'name' => 'Moved Checklist',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $checklist->id,
                    'scope_id' => $targetScope->id,
                    'code' => 'checklist-a',
                    'name' => 'Moved Checklist',
                ],
            ]);
    }
}
