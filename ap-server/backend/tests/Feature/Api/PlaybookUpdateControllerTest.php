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

        $this->assertPlaybookResponse(
            $response,
            $playbook->id,
            $scope->id,
            'updated-playbook',
            'Updated Playbook',
        );
    }

    public function test_it_moves_the_playbook_when_the_user_can_update_current_scope_and_create_in_target_scope(): void
    {
        $currentScope = $this->createTenantScope('tenant-current', 'Tenant Current');
        $targetScope = $this->createTenantScope('tenant-target', 'Tenant Target');

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

        $this->assertPlaybookResponse(
            $response,
            $playbook->id,
            $targetScope->id,
            'playbook-a',
            'Moved Playbook',
        );
    }

    private function assertPlaybookResponse($response, int $playbookId, int $scopeId, string $code, string $name): void
    {
        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $playbookId,
                    'scope_id' => $scopeId,
                    'code' => $code,
                    'name' => $name,
                ],
            ]);
    }

    private function createTenantScope(string $code, string $name): Scope
    {
        return Scope::query()->create([
            'layer' => 'tenant',
            'code' => $code,
            'name' => $name,
        ]);
    }
}
