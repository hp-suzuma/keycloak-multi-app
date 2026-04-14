<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class RequiredPermissionsMiddlewareTest extends AuthorizationApiTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('required_permissions:object.read')
            ->get('/api/test/permissions/read', fn () => response()->json(['status' => 'ok']));

        Route::middleware('required_permissions:object.read,object.execute')
            ->get('/api/test/permissions/read-execute', fn () => response()->json(['status' => 'ok']));
    }

    public function test_it_returns_forbidden_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/test/permissions/read');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.read'],
            ]);
    }

    public function test_it_allows_a_user_with_the_required_permission(): void
    {
        $this->assignRole('keycloak-user-1', 'tenant_viewer');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-1'))
            ->getJson('/api/test/permissions/read');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    public function test_it_returns_forbidden_when_any_required_permission_is_missing(): void
    {
        $this->assignRole('keycloak-user-2', 'tenant_viewer');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-2'))
            ->getJson('/api/test/permissions/read-execute');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.read', 'object.execute'],
            ]);
    }

    public function test_it_allows_a_user_when_all_required_permissions_are_present(): void
    {
        $this->assignRole('keycloak-user-3', 'server_admin');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-3'))
            ->getJson('/api/test/permissions/read-execute');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    private function assignRole(string $keycloakSub, string $roleSlug): void
    {
        ApUser::query()->create([
            'keycloak_sub' => $keycloakSub,
            'display_name' => 'AP User',
            'email' => $keycloakSub.'@example.com',
        ]);

        $scope = Scope::query()->create([
            'layer' => str($roleSlug)->before('_')->value(),
            'code' => $roleSlug.'-scope',
            'name' => $roleSlug.' scope',
        ]);
        $this->createUserRoleAssignment($keycloakSub, $roleSlug, $scope);
    }
}
