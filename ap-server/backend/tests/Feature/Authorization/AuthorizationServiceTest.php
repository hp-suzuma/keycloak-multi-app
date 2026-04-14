<?php

namespace Tests\Feature\Authorization;

use App\Models\ApUser;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorizationAssignments;
use Tests\TestCase;

class AuthorizationServiceTest extends TestCase
{
    use InteractsWithAuthorizationAssignments;
    use RefreshDatabase;

    public function test_it_returns_accessible_scope_ids_for_a_permission_including_descendants(): void
    {
        $serverScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-a',
            'name' => 'Server A',
        ]);

        $serviceScope = Scope::query()->create([
            'layer' => 'service',
            'code' => 'svc-a',
            'name' => 'Service A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serviceScope->id,
        ]);

        $otherScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-b',
            'name' => 'Server B',
        ]);

        $this->assignRole('keycloak-user-1', 'server_admin', $serverScope);

        $authorizationService = app(AuthorizationService::class);
        $currentUser = new CurrentUser(
            id: 'keycloak-user-1',
            name: 'KC User',
            email: 'kc-user@example.com',
        );

        $this->assertSame(
            [$serverScope->id, $serviceScope->id, $tenantScope->id],
            $authorizationService->accessibleScopeIds($currentUser, ['object.read']),
        );
        $this->assertTrue($authorizationService->canAccessScope($currentUser, 'object.read', $tenantScope->id));
        $this->assertFalse($authorizationService->canAccessScope($currentUser, 'object.read', $otherScope->id));
    }

    public function test_it_returns_an_empty_scope_list_when_the_user_lacks_the_required_permission(): void
    {
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
        ]);

        $this->assignRole('keycloak-user-2', 'tenant_viewer', $tenantScope);

        $authorizationService = app(AuthorizationService::class);
        $currentUser = new CurrentUser(
            id: 'keycloak-user-2',
            name: 'KC User 2',
            email: 'kc-user-2@example.com',
        );

        $this->assertSame([], $authorizationService->accessibleScopeIds($currentUser, ['object.update']));
        $this->assertFalse($authorizationService->canAccessScope($currentUser, 'object.update', $tenantScope->id));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
    }

    private function assignRole(string $keycloakSub, string $roleSlug, Scope $scope): void
    {
        ApUser::query()->create([
            'keycloak_sub' => $keycloakSub,
            'display_name' => 'AP User',
            'email' => $keycloakSub.'@example.com',
        ]);

        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);
    }
}
