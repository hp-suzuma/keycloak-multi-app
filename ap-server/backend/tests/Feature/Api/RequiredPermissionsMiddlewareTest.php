<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class RequiredPermissionsMiddlewareTest extends CreateAuthorizationApiTestCase
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

        $this->assertForbiddenResponse($response, ['object.read']);
    }

    public function test_it_allows_a_user_with_the_required_permission(): void
    {
        $this->assignRole('keycloak-user-1', 'tenant_viewer');

        $response = $this->withAccessToken('keycloak-user-1')
            ->getJson('/api/test/permissions/read');

        $this->assertOkStatusResponse($response);
    }

    public function test_it_returns_forbidden_when_any_required_permission_is_missing(): void
    {
        $this->assignRole('keycloak-user-2', 'tenant_viewer');

        $response = $this->withAccessToken('keycloak-user-2')
            ->getJson('/api/test/permissions/read-execute');

        $this->assertForbiddenResponse($response, ['object.read', 'object.execute']);
    }

    public function test_it_allows_a_user_when_all_required_permissions_are_present(): void
    {
        $this->assignRole('keycloak-user-3', 'server_admin');

        $response = $this->withAccessToken('keycloak-user-3')
            ->getJson('/api/test/permissions/read-execute');

        $this->assertOkStatusResponse($response);
    }

    /**
     * @param  array<int, string>  $requiredPermissions
     */
    private function assertForbiddenResponse($response, array $requiredPermissions): void
    {
        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => $requiredPermissions,
            ]);
    }

    private function assertOkStatusResponse($response): void
    {
        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }
}
