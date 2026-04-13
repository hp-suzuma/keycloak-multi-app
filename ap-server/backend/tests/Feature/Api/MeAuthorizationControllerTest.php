<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeAuthorizationControllerTest extends TestCase
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

    public function test_it_returns_null_authorization_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/me/authorization');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => null,
                'authorization' => null,
            ]);
    }

    public function test_it_returns_assignments_and_permissions_for_a_keycloak_user_managed_in_the_ap_database(): void
    {
        ApUser::query()->create([
            'keycloak_sub' => 'keycloak-user-1',
            'display_name' => 'AP User',
            'email' => 'ap-user@example.com',
        ]);

        $serverScope = Scope::query()->create([
            'layer' => 'server',
            'code' => 'srv-1',
            'name' => 'Server 1',
        ]);

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $serverAdminRole = Role::query()->where('slug', 'server_admin')->firstOrFail();
        $tenantViewerRole = Role::query()->where('slug', 'tenant_viewer')->firstOrFail();

        UserRoleAssignment::query()->create([
            'keycloak_sub' => 'keycloak-user-1',
            'role_id' => $serverAdminRole->id,
            'scope_id' => $serverScope->id,
        ]);

        UserRoleAssignment::query()->create([
            'keycloak_sub' => 'keycloak-user-1',
            'role_id' => $tenantViewerRole->id,
            'scope_id' => $tenantScope->id,
        ]);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-1',
            'aud' => ['ap-frontend'],
            'preferred_username' => 'kc-user',
            'email' => 'kc-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me/authorization');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => [
                    'id' => 'keycloak-user-1',
                    'name' => 'kc-user',
                    'email' => 'kc-user@example.com',
                ],
                'authorization' => [
                    'keycloak_sub' => 'keycloak-user-1',
                    'assignments' => [
                        [
                            'scope' => [
                                'id' => $serverScope->id,
                                'layer' => 'server',
                                'code' => 'srv-1',
                                'name' => 'Server 1',
                                'parent_scope_id' => null,
                            ],
                            'role' => [
                                'id' => $serverAdminRole->id,
                                'slug' => 'server_admin',
                                'name' => 'Server Admin',
                                'scope_layer' => 'server',
                                'permission_role' => 'admin',
                            ],
                            'permissions' => [
                                $this->permissionPayload('object.read'),
                                $this->permissionPayload('object.update'),
                                $this->permissionPayload('object.create'),
                                $this->permissionPayload('object.delete'),
                                $this->permissionPayload('object.execute'),
                            ],
                        ],
                        [
                            'scope' => [
                                'id' => $tenantScope->id,
                                'layer' => 'tenant',
                                'code' => 'tenant-a',
                                'name' => 'Tenant A',
                                'parent_scope_id' => $serverScope->id,
                            ],
                            'role' => [
                                'id' => $tenantViewerRole->id,
                                'slug' => 'tenant_viewer',
                                'name' => 'Tenant Viewer',
                                'scope_layer' => 'tenant',
                                'permission_role' => 'viewer',
                            ],
                            'permissions' => [
                                $this->permissionPayload('object.read'),
                            ],
                        ],
                    ],
                    'permissions' => [
                        'object.read',
                        'object.update',
                        'object.create',
                        'object.delete',
                        'object.execute',
                    ],
                ],
            ]);
    }

    public function test_it_returns_an_empty_assignment_list_when_the_keycloak_user_has_no_ap_record_yet(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-2',
            'aud' => ['ap-frontend'],
            'preferred_username' => 'kc-user-2',
            'email' => 'kc-user-2@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me/authorization');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => [
                    'id' => 'keycloak-user-2',
                    'name' => 'kc-user-2',
                    'email' => 'kc-user-2@example.com',
                ],
                'authorization' => [
                    'keycloak_sub' => 'keycloak-user-2',
                    'assignments' => [],
                    'permissions' => [],
                ],
            ]);
    }

    private function permissionPayload(string $slug): array
    {
        $permission = Permission::query()->where('slug', $slug)->firstOrFail();

        return [
            'id' => $permission->id,
            'slug' => $permission->slug,
            'name' => $permission->name,
        ];
    }

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
     * @param  array<int, array<string, mixed>>  $keys
     */
    private function fakeJwks(string $url, array $keys): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', $url);

        Http::fake([
            $url => Http::response([
                'keys' => $keys,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function buildJwk(string $kid, array $details): array
    {
        if (! isset($details['rsa']['n'], $details['rsa']['e'])) {
            $this->fail('Missing RSA details for JWK test payload.');
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }
}
