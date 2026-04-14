<?php

namespace Tests\Feature\Api;

use App\Models\ManagedObject;
use App\Models\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ObjectIndexControllerTest extends CreateAuthorizationApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_an_empty_list_when_the_user_has_object_read_permission(): void
    {
        $tenantScope = $this->assignRole('keycloak-user-objects', 'tenant_viewer');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-objects'))
            ->getJson('/api/objects');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => null,
                        'code' => null,
                        'name' => null,
                        'sort' => null,
                    ],
                ],
            ]);
    }

    public function test_it_returns_only_objects_in_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-server-admin', 'server_admin');

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

        $otherServerScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-b',
            'name' => 'Server B',
        ]);

        $serverObject = ManagedObject::query()->create([
            'scope_id' => $serverScope->id,
            'code' => 'srv-object',
            'name' => 'Server Object',
        ]);

        $tenantObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-object',
            'name' => 'Tenant Object',
        ]);

        ManagedObject::query()->create([
            'scope_id' => $otherServerScope->id,
            'code' => 'other-object',
            'name' => 'Other Object',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-server-admin'))
            ->getJson('/api/objects');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $serverObject->id,
                        'scope_id' => $serverScope->id,
                        'code' => 'srv-object',
                        'name' => 'Server Object',
                    ],
                    [
                        'id' => $tenantObject->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'tenant-object',
                        'name' => 'Tenant Object',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 2,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => null,
                        'code' => null,
                        'name' => null,
                        'sort' => null,
                    ],
                ],
            ]);
    }

    public function test_it_returns_only_descendant_objects_for_a_tenant_viewer(): void
    {
        $serverScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-a',
            'name' => 'Server A',
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $otherTenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'parent_scope_id' => $serverScope->id,
        ]);

        $this->assignRole('keycloak-user-tenant-viewer', 'tenant_viewer', $tenantScope);

        $tenantObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-a-object',
            'name' => 'Tenant A Object',
        ]);

        ManagedObject::query()->create([
            'scope_id' => $otherTenantScope->id,
            'code' => 'tenant-b-object',
            'name' => 'Tenant B Object',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-tenant-viewer'))
            ->getJson('/api/objects');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $tenantObject->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'tenant-a-object',
                        'name' => 'Tenant A Object',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 1,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => null,
                        'code' => null,
                        'name' => null,
                        'sort' => null,
                    ],
                ],
            ]);
    }

    public function test_it_applies_scope_and_code_filters_within_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-filter', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $otherScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-b',
            'name' => 'Server B',
        ]);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'target-object',
            'name' => 'Target Object',
        ]);

        ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'other-code',
            'name' => 'Other Object',
        ]);

        ManagedObject::query()->create([
            'scope_id' => $otherScope->id,
            'code' => 'target-object',
            'name' => 'Forbidden Object',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-filter'))
            ->getJson('/api/objects?scope_id='.$tenantScope->id.'&code=%20Target_Object%20');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $managedObject->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'target-object',
                        'name' => 'Target Object',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 1,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => $tenantScope->id,
                        'code' => 'target-object',
                        'name' => null,
                        'sort' => null,
                    ],
                ],
            ]);
    }

    public function test_it_paginates_results_without_leaking_inaccessible_data(): void
    {
        $serverScope = $this->assignRole('keycloak-user-page', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $firstObject = ManagedObject::query()->create([
            'scope_id' => $serverScope->id,
            'code' => 'object-1',
            'name' => 'Object 1',
        ]);

        ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'object-2',
            'name' => 'Object 2',
        ]);

        $thirdObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'object-3',
            'name' => 'Object 3',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-page'))
            ->getJson('/api/objects?page=2&per_page=2');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $thirdObject->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'object-3',
                        'name' => 'Object 3',
                    ],
                ],
                'meta' => [
                    'current_page' => 2,
                    'per_page' => 2,
                    'total' => 3,
                    'last_page' => 2,
                    'filters' => [
                        'scope_id' => null,
                        'code' => null,
                        'name' => null,
                        'sort' => null,
                    ],
                ],
            ]);
    }

    public function test_it_applies_name_filter_and_sort_within_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-sort', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $secondSortedObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'bbb',
            'name' => 'Target Zebra',
        ]);

        $firstSortedObject = ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'aaa',
            'name' => 'Target Alpha',
        ]);

        ManagedObject::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'ccc',
            'name' => 'Ignored Name',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-sort'))
            ->getJson('/api/objects?name=Target&sort=code');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $firstSortedObject->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'aaa',
                        'name' => 'Target Alpha',
                    ],
                    [
                        'id' => $secondSortedObject->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'bbb',
                        'name' => 'Target Zebra',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 2,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => null,
                        'code' => null,
                        'name' => 'Target',
                        'sort' => 'code',
                    ],
                ],
            ]);
    }

}
