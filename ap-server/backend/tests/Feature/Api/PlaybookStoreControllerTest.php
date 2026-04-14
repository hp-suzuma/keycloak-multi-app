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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbook-store'))
            ->postJson('/api/playbooks', [
                'scope_id' => $tenantScope->id,
                'code' => ' Tenant_Playbook ',
                'name' => 'Tenant Playbook',
            ]);

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => 1,
                    'scope_id' => $tenantScope->id,
                    'code' => 'tenant-playbook',
                    'name' => 'Tenant Playbook',
                ],
            ]);
    }

    public function test_it_returns_validation_errors_when_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-playbook-store-dup', 'tenant_admin');

        Playbook::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-playbook',
            'name' => 'Existing Playbook',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbook-store-dup'))
            ->postJson('/api/playbooks', [
                'scope_id' => $scope->id,
                'code' => ' Tenant_Playbook ',
                'name' => 'Duplicated Playbook',
            ]);

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'errors' => [
                    'code' => ['The code has already been taken within the target scope.'],
                ],
            ]);
    }

}
