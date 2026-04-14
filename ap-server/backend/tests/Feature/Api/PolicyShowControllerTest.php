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

class PolicyShowControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_policy_when_the_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-show', 'tenant_viewer');

        $policy = Policy::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-policy',
            'name' => 'Tenant Policy',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-policy-show'))
            ->getJson('/api/policies/'.$policy->id);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $policy->id,
                    'scope_id' => $scope->id,
                    'code' => 'tenant-policy',
                    'name' => 'Tenant Policy',
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
