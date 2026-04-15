<?php

namespace Tests\Feature\Api;

use App\Models\Playbook;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlaybookDeleteControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_playbook_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-playbook-delete', 'tenant_admin');

        $playbook = Playbook::query()->create([
            'scope_id' => $scope->id,
            'code' => 'playbook-a',
            'name' => 'Playbook A',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbook-delete'))
            ->deleteJson('/api/playbooks/'.$playbook->id);

        $response->assertNoContent();
    }
}
