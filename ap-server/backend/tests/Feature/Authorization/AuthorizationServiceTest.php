<?php

namespace Tests\Feature\Authorization;

use App\Models\Scope;
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
            'code' => 'srv-authz-a',
            'name' => 'Server A',
        ]);

        $serviceScope = Scope::query()->create([
            'layer' => 'service',
            'code' => 'svc-authz-a',
            'name' => 'Service A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-authz-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serviceScope->id,
        ]);

        $otherScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-authz-b',
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

    public function test_it_returns_only_granted_scope_ids_without_descendants(): void
    {
        $serverScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-authz-c',
            'name' => 'Server A',
        ]);

        $serviceScope = Scope::query()->create([
            'layer' => 'service',
            'code' => 'svc-authz-c',
            'name' => 'Service A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $this->assignRole('keycloak-user-3', 'server_user_manager', $serverScope);

        $authorizationService = app(AuthorizationService::class);
        $currentUser = new CurrentUser(
            id: 'keycloak-user-3',
            name: 'KC User 3',
            email: 'kc-user-3@example.com',
        );

        $this->assertSame(
            [$serverScope->id],
            $authorizationService->grantedScopeIds($currentUser, ['user.manage']),
        );
        $this->assertNotSame(
            [$serverScope->id, $serviceScope->id],
            $authorizationService->grantedScopeIds($currentUser, ['user.manage']),
        );
    }

    public function test_it_returns_an_empty_scope_list_when_the_user_lacks_the_required_permission(): void
    {
        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-authz-b',
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
        $this->createAuthorizationUser($keycloakSub);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);
    }
}
