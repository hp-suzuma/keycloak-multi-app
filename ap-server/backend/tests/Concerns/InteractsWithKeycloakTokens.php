<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait InteractsWithKeycloakTokens
{
    protected string $keycloakPrivateKey;

    /**
     * @var array<string, mixed>
     */
    protected array $keycloakPublicKeyDetails;

    protected function setUpKeycloakTokenAuth(
        string $issuer = 'https://sso.example.com/realms/ap',
        string $clientId = 'ap-frontend',
        string $jwksUrl = 'https://sso.example.com/realms/ap/protocol/openid-connect/certs',
    ): void {
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

        config()->set('services.keycloak.issuer', $issuer);
        config()->set('services.keycloak.client_id', $clientId);
        config()->set('services.keycloak.public_key', null);
        config()->set('services.keycloak.jwks_cache_ttl', 300);
        config()->set('services.keycloak.discovery_cache_ttl', 300);

        Http::preventStrayRequests();
        $this->fakeJwks($jwksUrl, [
            $this->buildJwk('keycloak-kid-1', $details),
        ]);
    }

    protected function buildAccessToken(string $subject): string
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
    protected function buildJwt(array $payload): string
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

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    protected function buildJwk(string $kid, array $details): array
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
     * @param  array<int, array<string, mixed>>  $keys
     */
    protected function fakeJwks(string $jwksUrl, array $keys): void
    {
        Cache::flush();
        config()->set('services.keycloak.jwks_url', $jwksUrl);

        Http::fake([
            $jwksUrl => Http::response(['keys' => $keys]),
        ]);
    }
}
