<?php

namespace Tests\Feature\Api;

use App\Models\ManagedObject;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ObjectShowControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_not_found_when_the_object_does_not_exist(): void
    {
        $this->assignRole('keycloak-user-1', 'tenant_viewer');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-1'))
            ->getJson('/api/objects/999999');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Not Found',
            ]);
    }

    public function test_it_returns_forbidden_when_the_object_scope_is_not_accessible(): void
    {
        $accessibleScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
        ]);

        $forbiddenScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-b',
            'name' => 'Tenant B',
        ]);

        $this->assignRole('keycloak-user-2', 'tenant_viewer', $accessibleScope);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $forbiddenScope->id,
            'code' => 'object-b',
            'name' => 'Object B',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-2'))
            ->getJson('/api/objects/'.$managedObject->id);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.read'],
                'scope_id' => $forbiddenScope->id,
            ]);
    }

    public function test_it_returns_the_object_when_the_scope_is_accessible(): void
    {
        $serverScope = $this->assignRole('keycloak-user-3', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'object-c',
            'name' => 'Object C',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-3'))
            ->getJson('/api/objects/'.$managedObject->id);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $managedObject->id,
                    'scope_id' => $tenantScope->id,
                    'code' => 'object-c',
                    'name' => 'Object C',
                ],
            ]);
    }

}
