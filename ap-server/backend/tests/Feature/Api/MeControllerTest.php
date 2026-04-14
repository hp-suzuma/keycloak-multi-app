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

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => null,
            ]);
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
                'current_user' => [
                    'id' => $user->id,
                    'name' => 'AP User',
                    'email' => 'ap-user@example.com',
                ],
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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => [
                    'id' => 'keycloak-user-1',
                    'name' => 'kc-user',
                    'email' => 'kc-user@example.com',
                ],
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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => [
                    'id' => 'keycloak-user-2',
                    'name' => 'kid-user@example.com',
                    'email' => 'kid-user@example.com',
                ],
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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => null,
            ]);
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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => null,
            ]);
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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => null,
            ]);
    }

    public function test_it_resolves_the_jwks_url_via_openid_connect_discovery(): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', null);

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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => [
                    'id' => 'keycloak-user-3',
                    'name' => 'discovery-user@example.com',
                    'email' => 'discovery-user@example.com',
                ],
            ]);
    }

    public function test_it_falls_back_to_the_default_jwks_path_when_discovery_fails(): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', null);

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

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response
            ->assertOk()
            ->assertExactJson([
                'current_user' => [
                    'id' => 'keycloak-user-4',
                    'name' => 'fallback-user@example.com',
                    'email' => 'fallback-user@example.com',
                ],
            ]);
    }
}
