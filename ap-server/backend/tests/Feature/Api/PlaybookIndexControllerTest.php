<?php

namespace Tests\Feature\Api;

use App\Models\ApUser;
use App\Models\Playbook;
use App\Models\Role;
use App\Models\Scope;
use App\Models\UserRoleAssignment;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaybookIndexControllerTest extends TestCase
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

    public function test_it_requires_the_object_read_permission(): void
    {
        $response = $this->getJson('/api/playbooks');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'required_permissions' => ['object.read'],
            ]);
    }

    public function test_it_returns_only_playbooks_in_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-playbooks', 'server_admin');

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

        $serverPlaybook = Playbook::query()->create([
            'scope_id' => $serverScope->id,
            'code' => 'server-runbook',
            'name' => 'Server Runbook',
        ]);

        $tenantPlaybook = Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'tenant-runbook',
            'name' => 'Tenant Runbook',
        ]);

        Playbook::query()->create([
            'scope_id' => $otherScope->id,
            'code' => 'forbidden-runbook',
            'name' => 'Forbidden Runbook',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbooks'))
            ->getJson('/api/playbooks');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $serverPlaybook->id,
                        'scope_id' => $serverScope->id,
                        'code' => 'server-runbook',
                        'name' => 'Server Runbook',
                    ],
                    [
                        'id' => $tenantPlaybook->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'tenant-runbook',
                        'name' => 'Tenant Runbook',
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

    public function test_it_applies_filter_and_sort_within_accessible_scopes(): void
    {
        $serverScope = $this->assignRole('keycloak-user-playbooks-filter', 'server_admin');

        $tenantScope = Scope::query()->create([
            'layer' => 'tenant',
            'code' => 'tenant-a',
            'name' => 'Tenant A',
            'parent_scope_id' => $serverScope->id,
        ]);

        $firstPlaybook = Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'alpha-playbook',
            'name' => 'Target Alpha',
        ]);

        $secondPlaybook = Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'zebra-playbook',
            'name' => 'Target Zebra',
        ]);

        Playbook::query()->create([
            'scope_id' => $tenantScope->id,
            'code' => 'ignored-playbook',
            'name' => 'Ignored Name',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->buildAccessToken('keycloak-user-playbooks-filter'))
            ->getJson('/api/playbooks?scope_id='.$tenantScope->id.'&name=Target&sort=code');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $firstPlaybook->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'alpha-playbook',
                        'name' => 'Target Alpha',
                    ],
                    [
                        'id' => $secondPlaybook->id,
                        'scope_id' => $tenantScope->id,
                        'code' => 'zebra-playbook',
                        'name' => 'Target Zebra',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 2,
                    'last_page' => 1,
                    'filters' => [
                        'scope_id' => $tenantScope->id,
                        'code' => null,
                        'name' => 'Target',
                        'sort' => 'code',
                    ],
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
