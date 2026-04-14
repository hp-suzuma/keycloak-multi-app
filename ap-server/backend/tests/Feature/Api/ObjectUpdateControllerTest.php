<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\ManagedObject;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObjectUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $keycloakPrivateKey;

    /**
     * @var array<string, mixed>
     */
    private array $keycloakPublicKeyDetails;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($keyPair === false) {
            $this->fail('Failed to generate RSA key pair for Keycloak token tests.');
        }

        openssl_pkey_export($keyPair, $privateKey);
        $details = openssl_pkey_get_details($keyPair);

        if ($privateKey === false || ! is_array($details) || ! isset($details['key'])) {
            $this->fail('Failed to export RSA key pair for Keycloak token tests.');
        }

        $this->keycloakPrivateKey = $privateKey;
        $this->keycloakPublicKeyDetails = $details;

        config()->set('services.keycloak.issuer', 'https://sso.example.com/realms/ap');
        config()->set('services.keycloak.client_id', 'ap-frontend');
        config()->set('services.keycloak.public_key', null);
        config()->set('services.keycloak.jwks_cache_ttl', 300);
        config()->set('services.keycloak.discovery_cache_ttl', 300);

        Http::preventStrayRequests();
        $this->fakeJwks('https://sso.example.com/realms/ap/protocol/openid-connect/certs', [
            $this->buildJwk('keycloak-kid-1', $details),
        ]);

        $this->seed(AuthorizationSeeder::class);
    }

    public function test_it_requires_the_object_update_permission(): void
    {
        $scope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
        ]);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'object-a',
            'name' => 'Object A',
        ]);

        $response = $this->patchJson('/api/objects/'.$managedObject->id, [
            'name' => 'Updated Object A',
        ]);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.update'],
            ]);
    }

    public function test_it_returns_validation_errors_for_invalid_payloads(): void
    {
        $scope = $this->assignRole('keycloak-user-1', 'tenant_operator');

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'object-a',
            'name' => 'Object A',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-1'))
            ->patchJson('/api/objects/'.$managedObject->id, [
                'name' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_returns_validation_errors_when_no_fields_are_provided(): void
    {
        $scope = $this->assignRole('keycloak-user-empty', 'tenant_operator');

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'object-empty',
            'name' => 'Object Empty',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-empty'))
            ->patchJson('/api/objects/'.$managedObject->id, []);

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'errors' => [
                    'payload' => ['At least one of scope_id, code, or name is required.'],
                ],
            ]);
    }

    public function test_it_returns_not_found_when_the_object_does_not_exist(): void
    {
        $this->assignRole('keycloak-user-2', 'tenant_operator');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-2'))
            ->patchJson('/api/objects/999999', [
                'name' => 'Updated Name',
            ]);

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

        $this->assignRole('keycloak-user-3', 'tenant_operator', $accessibleScope);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $forbiddenScope->id,
            'code' => 'object-b',
            'name' => 'Object B',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-3'))
            ->patchJson('/api/objects/'.$managedObject->id, [
                'name' => 'Updated Object B',
            ]);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.update'],
                'scope_id' => $forbiddenScope->id,
            ]);
    }

    public function test_it_updates_the_object_when_the_scope_is_accessible(): void
    {
        $serverScope = $this->assignRole('keycloak-user-4', 'server_operator');

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
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-4'))
            ->patchJson('/api/objects/'.$managedObject->id, [
                'code' => ' Updated_Object_C ',
                'name' => 'Updated Object C',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $managedObject->id,
                    'scope_id' => $tenantScope->id,
                    'code' => 'updated-object-c',
                    'name' => 'Updated Object C',
                ],
            ]);

        $this->assertSame('Updated Object C', $managedObject->fresh()->name);
        $this->assertSame('updated-object-c', $managedObject->fresh()->code);
    }

    public function test_it_moves_the_object_when_the_user_can_update_current_scope_and_create_in_target_scope(): void
    {
        $currentScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-current',
            'name' => 'Tenant Current',
        ]);

        $targetScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-target',
            'name' => 'Tenant Target',
        ]);

        $this->assignRole('keycloak-user-move', 'tenant_operator', $currentScope);
        $this->assignRole('keycloak-user-move', 'tenant_admin', $targetScope);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $currentScope->id,
            'code' => 'object-move',
            'name' => 'Object Move',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-move'))
            ->patchJson('/api/objects/'.$managedObject->id, [
                'scope_id' => $targetScope->id,
                'name' => 'Moved Object',
            ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $managedObject->id,
                    'scope_id' => $targetScope->id,
                    'code' => 'object-move',
                    'name' => 'Moved Object',
                ],
            ]);

        $this->assertDatabaseHas('objects', [
            'id' => $managedObject->id,
            'scope_id' => $targetScope->id,
            'name' => 'Moved Object',
        ]);
    }

    public function test_it_returns_forbidden_when_the_user_cannot_create_in_the_target_scope_for_move(): void
    {
        $currentScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-current',
            'name' => 'Tenant Current',
        ]);

        $targetScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-target',
            'name' => 'Tenant Target',
        ]);

        $this->assignRole('keycloak-user-move-fail', 'tenant_operator', $currentScope);
        $this->assignRole('keycloak-user-move-fail', 'tenant_viewer', $targetScope);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $currentScope->id,
            'code' => 'object-move-fail',
            'name' => 'Object Move Fail',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-move-fail'))
            ->patchJson('/api/objects/'.$managedObject->id, [
                'scope_id' => $targetScope->id,
            ]);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.create'],
                'scope_id' => $targetScope->id,
            ]);
    }

    public function test_it_returns_validation_errors_when_the_target_scope_and_code_are_duplicated(): void
    {
        $scope = $this->assignRole('keycloak-user-dup-update', 'tenant_admin');

        ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'duplicated-code',
            'name' => 'Existing Object',
        ]);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $scope->id,
            'code' => 'editable-code',
            'name' => 'Editable Object',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-dup-update'))
            ->patchJson('/api/objects/'.$managedObject->id, [
                'code' => ' Duplicated_Code ',
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

    private function buildAccessToken(string $subject): string
    {
        return $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => $subject,
            'aud' => ['ap-frontend'],
            'preferred_username' => $subject,
            'email' => $subject.'@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildJwt(array $payload): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'keycloak-kid-1'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signedPart = $encodedHeader.'.'.$encodedPayload;

        $signature = '';
        $result = openssl_sign($signedPart, $signature, $this->keycloakPrivateKey, OPENSSL_ALGO_SHA256);

        if ($result !== true) {
            $this->fail('Failed to sign RSA JWT for Keycloak token test.');
        }

        return implode('.', [
            $encodedHeader,
            $encodedPayload,
            $this->base64UrlEncode($signature),
        ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    private function buildJwk(string $kid, array $details): array
    {
        $rsa = $details['rsa'] ?? null;

        if (! is_array($rsa) || ! isset($rsa['n'], $rsa['e'])) {
            $this->fail('Failed to read RSA key details for JWKS test setup.');
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => $this->base64UrlEncode($rsa['n']),
            'e' => $this->base64UrlEncode($rsa['e']),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $keys
     */
    private function fakeJwks(string $jwksUrl, array $keys): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', $jwksUrl);

        Http::fake([
            $jwksUrl => Http::response(['keys' => $keys]),
        ]);
    }
}
