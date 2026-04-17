<?php

namespace App\Services\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class KeycloakJwksPublicKeyResolver
{
    public function resolve(?string $kid): ?string
    {
        if (! is_string($kid) || $kid === '') {
            return null;
        }

        $keys = $this->fetchKeys();

        if ($keys === null) {
            return null;
        }

        foreach ($keys as $key) {
            if (! is_array($key) || ($key['kid'] ?? null) !== $kid) {
                continue;
            }

            return $this->convertJwkToPem($key);
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchKeys(): ?array
    {
        $jwksUrl = $this->jwksUrl();

        if ($jwksUrl === null) {
            return null;
        }

        $ttl = (int) config('services.keycloak.jwks_cache_ttl', 300);
        $cacheKey = 'keycloak.jwks.'.sha1($jwksUrl);

        return Cache::remember($cacheKey, now()->addSeconds(max($ttl, 1)), function () use ($jwksUrl): ?array {
            try {
                $response = Http::acceptJson()->timeout(5)->get($jwksUrl);
            } catch (ConnectionException) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $keys = $response->json('keys');

            return is_array($keys) ? $keys : null;
        });
    }

    private function jwksUrl(): ?string
    {
        $configuredUrl = config('services.keycloak.jwks_url');

        if (is_string($configuredUrl) && $configuredUrl !== '') {
            return $configuredUrl;
        }

        $internalBaseUrl = config('services.keycloak.internal_base_url');

        if (is_string($internalBaseUrl) && $internalBaseUrl !== '') {
            return rtrim($internalBaseUrl, '/').'/protocol/openid-connect/certs';
        }

        $discoveredUrl = $this->discoverJwksUrl();

        if ($discoveredUrl !== null) {
            return $discoveredUrl;
        }

        $issuer = config('services.keycloak.issuer');

        if (! is_string($issuer) || $issuer === '') {
            return null;
        }

        return rtrim($issuer, '/').'/protocol/openid-connect/certs';
    }

    private function discoverJwksUrl(): ?string
    {
        $issuer = config('services.keycloak.issuer');

        if (! is_string($issuer) || $issuer === '') {
            return null;
        }

        $discoveryUrl = rtrim($issuer, '/').'/.well-known/openid-configuration';
        $cacheKey = 'keycloak.discovery.'.sha1($discoveryUrl);
        $ttl = (int) config('services.keycloak.discovery_cache_ttl', 300);

        return Cache::remember($cacheKey, now()->addSeconds(max($ttl, 1)), function () use ($discoveryUrl): ?string {
            try {
                $response = Http::acceptJson()->timeout(5)->get($discoveryUrl);
            } catch (ConnectionException) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $jwksUri = $response->json('jwks_uri');

            return is_string($jwksUri) && $jwksUri !== '' ? $jwksUri : null;
        });
    }

    /**
     * @param  array<string, mixed>  $jwk
     */
    private function convertJwkToPem(array $jwk): ?string
    {
        if (($jwk['kty'] ?? null) !== 'RSA') {
            return null;
        }

        $modulus = $this->decodeBase64Url($jwk['n'] ?? null);
        $exponent = $this->decodeBase64Url($jwk['e'] ?? null);

        if ($modulus === null || $exponent === null) {
            return null;
        }

        $modulusInteger = $this->encodeAsn1Integer($modulus);
        $exponentInteger = $this->encodeAsn1Integer($exponent);
        $rsaPublicKey = $this->encodeAsn1Sequence($modulusInteger.$exponentInteger);
        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');

        if ($algorithmIdentifier === false) {
            return null;
        }

        $subjectPublicKeyInfo = $this->encodeAsn1Sequence(
            $algorithmIdentifier.$this->encodeAsn1BitString($rsaPublicKey)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function encodeAsn1Sequence(string $value): string
    {
        return "\x30".$this->encodeAsn1Length(strlen($value)).$value;
    }

    private function encodeAsn1Integer(string $value): string
    {
        if ($value === '') {
            return "\x02\x01\x00";
        }

        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00".$value;
        }

        return "\x02".$this->encodeAsn1Length(strlen($value)).$value;
    }

    private function encodeAsn1BitString(string $value): string
    {
        return "\x03".$this->encodeAsn1Length(strlen($value) + 1)."\x00".$value;
    }

    private function encodeAsn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = '';
        $remaining = $length;

        while ($remaining > 0) {
            $encoded = chr($remaining & 0xff).$encoded;
            $remaining >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    private function decodeBase64Url(mixed $encoded): ?string
    {
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $remainder = strlen($encoded) % 4;

        if ($remainder > 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
