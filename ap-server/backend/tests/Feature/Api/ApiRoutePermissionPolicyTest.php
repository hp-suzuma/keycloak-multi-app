<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRoutePermissionPolicyTest extends TestCase
{
    public function test_current_public_and_introspection_routes_do_not_require_permissions(): void
    {
        $this->assertRouteDoesNotRequirePermissions('api.health');
        $this->assertRouteDoesNotRequirePermissions('api.me');
        $this->assertRouteDoesNotRequirePermissions('api.me.authorization');
    }

    private function assertRouteDoesNotRequirePermissions(string $routeName): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, sprintf('Route [%s] was not found.', $routeName));
        $this->assertNotContains(
            'required_permissions',
            $route->middleware(),
            sprintf('Route [%s] should stay permission-free.', $routeName),
        );
    }
}
