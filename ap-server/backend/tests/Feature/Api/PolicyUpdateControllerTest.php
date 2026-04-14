<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Policy;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PolicyUpdateControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_updates_a_policy_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-update', 'tenant_operator');

        $policy = Policy::query()->create([
            'scope_id' => $scope->id,
            'code' => 'policy-a',
            'name' => 'Policy A',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-policy-update'))
            ->patchJson('/api/policies/'.$policy->id, [
                'code' => ' Updated_Policy ',
                'name' => 'Updated Policy',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $policy->id,
                    'scope_id' => $scope->id,
                    'code' => 'updated-policy',
                    'name' => 'Updated Policy',
                ],
            ]);
    }

    public function test_it_rejects_moving_a_policy_to_another_scope(): void
    {
        $currentScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-policy-current',
            'name' => 'Tenant Policy Current',
        ]);
        $targetScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-policy-target',
            'name' => 'Tenant Policy Target',
        ]);

        $this->assignRole('keycloak-user-policy-move', 'tenant_operator', $currentScope);
        $this->assignRole('keycloak-user-policy-move', 'tenant_admin', $targetScope);

        $policy = Policy::query()->create([
            'scope_id' => $currentScope->id,
            'code' => 'policy-a',
            'name' => 'Policy A',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-policy-move'))
            ->patchJson('/api/policies/'.$policy->id, [
                'scope_id' => $targetScope->id,
                'name' => 'Moved Policy',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Validation failed',
                'errors' => [
                    'scope_id' => ['Policy scope cannot be changed after creation.'],
                ],
            ]);
    }

    private function assignRole(string $keycloakSub, string $roleSlug, ?Scope $scope = null): Scope
    {
        ApUser::query()->updateOrCreate([
            'keycloak_sub' => $keycloakSub,
        ], [
            'display_name' => 'AP User',
            'email' => $keycloakSub.'@example.com',
        ]);

        $scope ??= $this->createDefaultScopeForRole($keycloakSub, $roleSlug);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);

        return $scope;
    }
}
