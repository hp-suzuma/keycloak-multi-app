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

class PolicyStoreControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_policy_when_the_target_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-store', 'tenant_admin');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-policy-store'))
            ->postJson('/api/policies', [
                'scope_id' => $scope->id,
                'code' => ' Tenant_Policy ',
                'name' => 'Tenant Policy',
            ]);

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => 1,
                    'scope_id' => $scope->id,
                    'code' => 'tenant-policy',
                    'name' => 'Tenant Policy',
                ],
            ]);

        $this->assertDatabaseHas('policies', [
            'scope_id' => $scope->id,
            'code' => 'tenant-policy',
        ]);
    }

    public function test_it_returns_validation_errors_when_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-policy-store-dup', 'tenant_admin');

        Policy::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-policy',
            'name' => 'Existing Policy',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-policy-store-dup'))
            ->postJson('/api/policies', [
                'scope_id' => $scope->id,
                'code' => ' Tenant_Policy ',
                'name' => 'Duplicated Policy',
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

    private function assignRole(string $keycloakSub, string $roleSlug, ?Scope $scope = null): Scope
    {
        ApUser::query()->updateOrCreate([
            'keycloak_sub' => $keycloakSub,
        ], [
            'display_name' => 'AP User',
            'email' => $keycloakSub.'@example.com',
        ]);

        $scope ??= Scope::query()->create([
            'layer' => str($roleSlug)->before('_')->value(),
            'code' => $roleSlug.'-scope-'.$keycloakSub,
            'name' => $roleSlug.' scope',
        ]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        UserRoleAssignment::query()->create([
            'keycloak_sub' => $keycloakSub,
            'role_id' => $role->id,
            'scope_id' => $scope->id,
        ]);

        return $scope;
    }
}
