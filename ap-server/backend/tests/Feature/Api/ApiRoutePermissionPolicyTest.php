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

    public function test_business_routes_declare_required_permissions(): void
    {
        $this->assertRouteRequiresPermissions('api.objects.index', 'required_permissions:object.read');
        $this->assertRouteRequiresPermissions('api.playbooks.index', 'required_permissions:object.read');
        $this->assertRouteRequiresPermissions('api.playbooks.store', 'required_permissions:object.create');
        $this->assertRouteRequiresPermissions('api.playbooks.show', 'required_permissions:object.read');
        $this->assertRouteRequiresPermissions('api.playbooks.update', 'required_permissions:object.update');
        $this->assertRouteRequiresPermissions('api.playbooks.destroy', 'required_permissions:object.delete');
        $this->assertRouteRequiresPermissions('api.policies.index', 'required_permissions:object.read');
        $this->assertRouteRequiresPermissions('api.policies.store', 'required_permissions:object.create');
        $this->assertRouteRequiresPermissions('api.policies.show', 'required_permissions:object.read');
        $this->assertRouteRequiresPermissions('api.policies.update', 'required_permissions:object.update');
        $this->assertRouteRequiresPermissions('api.policies.destroy', 'required_permissions:object.delete');
        $this->assertRouteRequiresPermissions('api.objects.store', 'required_permissions:object.create');
        $this->assertRouteRequiresPermissions('api.objects.show', 'required_permissions:object.read');
        $this->assertRouteRequiresPermissions('api.objects.update', 'required_permissions:object.update');
        $this->assertRouteRequiresPermissions('api.objects.destroy', 'required_permissions:object.delete');
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

    private function assertRouteRequiresPermissions(string $routeName, string $requiredMiddleware): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, sprintf('Route [%s] was not found.', $routeName));
        $this->assertContains(
            $requiredMiddleware,
            $route->middleware(),
            sprintf('Route [%s] should declare [%s].', $routeName, $requiredMiddleware),
        );
    }
}
