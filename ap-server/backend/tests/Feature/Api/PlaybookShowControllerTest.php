<?php

namespace Tests\Feature\Api;

use App\Models\Playbook;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlaybookShowControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_requires_the_object_read_permission(): void
    {
        $scope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
        ]);

        $playbook = Playbook::query()->create([
            'scope_id' => $scope->id,
            'code' => 'playbook-a',
            'name' => 'Playbook A',
        ]);

        $response = $this->getJson('/api/playbooks/'.$playbook->id);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.read'],
            ]);
    }

    public function test_it_returns_the_playbook_when_the_scope_is_accessible(): void
    {
        $serverScope = $this->assignRole('keycloak-user-playbook-show', 'server_admin');
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $playbook = Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-playbook',
            'name' => 'Tenant Playbook',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbook-show'))
            ->getJson('/api/playbooks/'.$playbook->id);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $playbook->id,
                    'scope_id' => $tenantScope->id,
                    'code' => 'tenant-playbook',
                    'name' => 'Tenant Playbook',
                ],
            ]);
    }

}
