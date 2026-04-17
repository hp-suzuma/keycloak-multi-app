<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MeControllerTest extends KeycloakApiTestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_null_current_user_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/me');

        $this->assertNullCurrentUserResponse($response);
    }

    public function test_it_returns_the_current_user_when_authenticated(): void
    {
        $user = User::factory()->create([
            'name' => 'AP User',
            'email' => 'ap-user@example.com',
        ]);

        $response = $this->actingAs($user)->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload($user->id, 'AP User', 'ap-user@example.com'),
            ]);
    }

    public function test_it_returns_the_current_user_from_a_keycloak_bearer_token(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-1',
            'aud' => ['account', 'ap-frontend'],
            'azp' => 'ap-frontend',
            'preferred_username' => 'kc-user',
            'email' => 'kc-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-1', 'kc-user', 'kc-user@example.com'),
            ]);
    }

    public function test_it_reads_a_keycloak_bearer_token_from_redirect_http_authorization(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-redirect',
            'aud' => ['ap-frontend'],
            'azp' => 'ap-frontend',
            'preferred_username' => 'redirect-user',
            'email' => 'redirect-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this
            ->withServerVariables([
                'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ])
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-redirect', 'redirect-user', 'redirect-user@example.com'),
            ]);
    }

    public function test_it_reads_a_keycloak_bearer_token_from_authorization_server_variable(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-authorization',
            'aud' => ['ap-frontend'],
            'azp' => 'ap-frontend',
            'preferred_username' => 'authorization-user',
            'email' => 'authorization-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this
            ->withServerVariables([
                'AUTHORIZATION' => 'Bearer '.$token,
            ])
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-authorization', 'authorization-user', 'authorization-user@example.com'),
            ]);
    }

    public function test_it_reads_a_keycloak_bearer_token_from_forwarded_authorization_server_variable(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-forwarded',
            'aud' => ['ap-frontend'],
            'azp' => 'ap-frontend',
            'preferred_username' => 'forwarded-user',
            'email' => 'forwarded-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this
            ->withServerVariables([
                'HTTP_X_FORWARDED_AUTHORIZATION' => 'Bearer '.$token,
            ])
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-forwarded', 'forwarded-user', 'forwarded-user@example.com'),
            ]);
    }

    public function test_it_reads_a_keycloak_bearer_token_from_the_access_token_query_parameter(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-query',
            'aud' => ['ap-frontend'],
            'azp' => 'ap-frontend',
            'preferred_username' => 'query-user',
            'email' => 'query-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->getJson('/api/me?access_token='.urlencode($token));

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-query', 'query-user', 'query-user@example.com'),
            ]);
    }

    public function test_it_selects_the_matching_jwks_key_by_kid(): void
    {
        $secondaryKeyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($secondaryKeyPair === false) {
            $this->fail('Failed to generate secondary RSA key pair for JWKS test.');
        }

        $secondaryDetails = openssl_pkey_get_details($secondaryKeyPair);

        if (! is_array($secondaryDetails) || ! isset($secondaryDetails['rsa'])) {
            $this->fail('Failed to export secondary RSA public key details for JWKS test.');
        }

        Cache::flush();
        $this->fakeJwks('https://sso.example.com/realms/ap/protocol/openid-connect/certs', [
            $this->buildJwk('unused-kid', $secondaryDetails),
            $this->buildJwk('keycloak-kid-1', $this->keycloakPublicKeyDetails),
        ]);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-2',
            'aud' => ['ap-frontend'],
            'email' => 'kid-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-2', 'kid-user@example.com', 'kid-user@example.com'),
            ]);
    }

    public function test_it_rejects_a_keycloak_bearer_token_with_an_invalid_issuer(): void
    {
        $token = $this->buildJwt([
            'iss' => 'https://invalid.example.com/realms/ap',
            'sub' => 'keycloak-user-1',
            'aud' => ['ap-frontend'],
            'email' => 'kc-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $this->assertNullCurrentUserResponse($response);
    }

    public function test_it_rejects_an_unsigned_keycloak_bearer_token(): void
    {
        $header = ['alg' => 'none', 'typ' => 'JWT'];
        $payload = [
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-1',
            'aud' => ['ap-frontend'],
            'email' => 'kc-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ];

        $token = implode('.', [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode('signature'),
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $this->assertNullCurrentUserResponse($response);
    }

    public function test_it_rejects_a_keycloak_token_when_the_kid_is_not_found_in_jwks(): void
    {
        $this->fakeJwks('https://sso.example.com/realms/ap/protocol/openid-connect/certs-empty', []);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-1',
            'aud' => ['ap-frontend'],
            'email' => 'kc-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $this->assertNullCurrentUserResponse($response);
    }

    public function test_it_resolves_the_jwks_url_via_openid_connect_discovery(): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', null);
        config()->set('services.keycloak.internal_base_url', null);

        Http::fake([
            'https://sso.example.com/realms/ap/.well-known/openid-configuration' => Http::response([
                'jwks_uri' => 'https://sso.example.com/realms/ap/discovered-certs',
            ]),
            'https://sso.example.com/realms/ap/discovered-certs' => Http::response([
                'keys' => [
                    $this->buildJwk('keycloak-kid-1', $this->keycloakPublicKeyDetails),
                ],
            ]),
        ]);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-3',
            'aud' => ['ap-frontend'],
            'email' => 'discovery-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-3', 'discovery-user@example.com', 'discovery-user@example.com'),
            ]);
    }

    public function test_it_falls_back_to_the_default_jwks_path_when_discovery_fails(): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', null);
        config()->set('services.keycloak.internal_base_url', null);

        Http::fake([
            'https://sso.example.com/realms/ap/.well-known/openid-configuration' => Http::response([], 404),
            'https://sso.example.com/realms/ap/protocol/openid-connect/certs' => Http::response([
                'keys' => [
                    $this->buildJwk('keycloak-kid-1', $this->keycloakPublicKeyDetails),
                ],
            ]),
        ]);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-4',
            'aud' => ['ap-frontend'],
            'email' => 'fallback-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-4', 'fallback-user@example.com', 'fallback-user@example.com'),
            ]);
    }

    public function test_it_uses_the_internal_keycloak_base_url_when_jwks_url_is_not_configured(): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', null);
        config()->set('services.keycloak.internal_base_url', 'http://keycloak-internal.test/realms/ap');

        Http::fake([
            'http://keycloak-internal.test/realms/ap/protocol/openid-connect/certs' => Http::response([
                'keys' => [
                    $this->buildJwk('keycloak-kid-1', $this->keycloakPublicKeyDetails),
                ],
            ]),
        ]);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-5',
            'aud' => ['ap-frontend'],
            'email' => 'internal-base-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-5', 'internal-base-user@example.com', 'internal-base-user@example.com'),
            ]);
    }

    public function test_it_falls_back_to_the_default_jwks_path_when_discovery_connection_fails(): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', null);
        config()->set('services.keycloak.internal_base_url', null);

        Http::fake([
            'https://sso.example.com/realms/ap/.well-known/openid-configuration' => Http::failedConnection(),
            'https://sso.example.com/realms/ap/protocol/openid-connect/certs' => Http::response([
                'keys' => [
                    $this->buildJwk('keycloak-kid-1', $this->keycloakPublicKeyDetails),
                ],
            ]),
        ]);

        $token = $this->buildJwt([
            'iss' => 'https://sso.example.com/realms/ap',
            'sub' => 'keycloak-user-6',
            'aud' => ['ap-frontend'],
            'email' => 'connection-fallback-user@example.com',
            'exp' => now()->addMinutes(5)->timestamp,
        ]);

        $response = $this->withBearerToken($token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => $this->currentUserPayload('keycloak-user-6', 'connection-fallback-user@example.com', 'connection-fallback-user@example.com'),
            ]);
    }

    private function assertNullCurrentUserResponse($response): void
    {
        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => null,
            ]);
    }

    private function currentUserPayload(int|string $id, string $name, string $email): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];
    }
}
