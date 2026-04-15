<?php

namespace Tests\Feature\Api;

use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PolicyDeleteControllerTest extends UpsertAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_policy_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-delete', 'tenant_admin');

        $policy = Policy::query()->create([
            'scope_id' => $scope->id,
            'code' => 'policy-a',
            'name' => 'Policy A',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-policy-delete'))
            ->deleteJson('/api/policies/'.$policy->id);

        $response->assertNoContent();
    }
}
