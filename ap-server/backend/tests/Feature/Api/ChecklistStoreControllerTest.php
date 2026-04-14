<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Checklist;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChecklistStoreControllerTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_checklist_when_the_target_scope_is_accessible(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-store', 'tenant_admin');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-checklist-store'))
            ->postJson('/api/checklists', [
                'scope_id' => $scope->id,
                'code' => ' Tenant_Checklist ',
                'name' => 'Tenant Checklist',
            ]);

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => 1,
                    'scope_id' => $scope->id,
                    'code' => 'tenant-checklist',
                    'name' => 'Tenant Checklist',
                ],
            ]);

        $this->assertDatabaseHas('checklists', [
            'scope_id' => $scope->id,
            'code' => 'tenant-checklist',
        ]);
    }

    public function test_it_returns_validation_errors_when_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-checklist-store-dup', 'tenant_admin');

        Checklist::query()->create([
            'scope_id' => $scope->id,
            'code' => 'tenant-checklist',
            'name' => 'Existing Checklist',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-checklist-store-dup'))
            ->postJson('/api/checklists', [
                'scope_id' => $scope->id,
                'code' => ' Tenant_Checklist ',
                'name' => 'Duplicated Checklist',
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

        $scope ??= $this->createDefaultScopeForRole($keycloakSub, $roleSlug);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);

        return $scope;
    }
}
