<?php

namespace Tests\Feature\Api;

use App\Models\Playbook;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlaybookUpdateControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_updates_a_playbook_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-playbook-update', 'tenant_operator');

        $playbook = Playbook::query()->create([
            'scope_id' => $scope->id,
            'code' => 'playbook-a',
            'name' => 'Playbook A',
        ]);

        $response = $this->withAccessToken('keycloak-user-playbook-update')
            ->patchJson('/api/playbooks/'.$playbook->id, [
                'code' => ' Updated_Playbook ',
                'name' => 'Updated Playbook',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $playbook->id,
                    'scope_id' => $scope->id,
                    'code' => 'updated-playbook',
                    'name' => 'Updated Playbook',
                ],
            ]);
    }

    public function test_it_moves_the_playbook_when_the_user_can_update_current_scope_and_create_in_target_scope(): void
    {
        $currentScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-current',
            'name' => 'Tenant Current',
        ]);
        $targetScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-target',
            'name' => 'Tenant Target',
        ]);

        $this->assignRole('keycloak-user-playbook-move', 'tenant_operator', $currentScope);
        $this->assignRole('keycloak-user-playbook-move', 'tenant_admin', $targetScope);

        $playbook = Playbook::query()->create([
            'scope_id' => $currentScope->id,
            'code' => 'playbook-a',
            'name' => 'Playbook A',
        ]);

        $response = $this->withAccessToken('keycloak-user-playbook-move')
            ->patchJson('/api/playbooks/'.$playbook->id, [
                'scope_id' => $targetScope->id,
                'name' => 'Moved Playbook',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $playbook->id,
                    'scope_id' => $targetScope->id,
                    'code' => 'playbook-a',
                    'name' => 'Moved Playbook',
                ],
            ]);
    }
}
