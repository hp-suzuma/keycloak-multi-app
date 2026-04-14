<?php

namespace Tests\Feature\Api;

use App\Models\Checklist;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChecklistDeleteControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_checklist_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-delete', 'tenant_admin');

        $checklist = Checklist::query()->create([
            'scope_id' => $scope->id,
            'code' => 'checklist-a',
            'name' => 'Checklist A',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-checklist-delete'))
            ->deleteJson('/api/checklists/'.$checklist->id);

        $response->assertNoContent();
    }

}
