<?php

namespace App\Services\Auth;

use Illuminate\Support\Arr;

class KeycloakTokenCurrentUserResolver
{
    public function __construct(
        private readonly KeycloakJwksPublicKeyResolver $keycloakJwksPublicKeyResolver,
    ) {
    }

    public function resolveFromBearerToken(?string $token): ?CurrentUser
    {
        if ($token === null || $token === '') {
            return null;
        }

        $parsedToken = $this->parseToken($token);

        if ($parsedToken === null) {
            return null;
        }

        ['header' => $header, 'claims' => $claims, 'signed_part' => $signedPart, 'signature' => $signature] = $parsedToken;

        if (! $this->isSupportedHeader($header)) {
            return null;
        }

        if (! $this->hasValidClaims($claims)) {
            return null;
        }

        if (! $this->hasValidSignature($header, $signedPart, $signature)) {
            return null;
        }

        $id = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? $claims['preferred_username'] ?? $email;

        if (! is_string($id) || ! is_string($email) || ! is_string($name)) {
            return null;
        }

        return new CurrentUser(
            id: $id,
            name: $name,
            email: $email,
        );
    }

    /**
     * @return array{
     *     header: array<string, mixed>,
     *     claims: array<string, mixed>,
     *     signed_part: string,
     *     signature: string
     * }|null
     */
    private function parseToken(string $token): ?array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $headerJson = $this->decodeBase64Url($encodedHeader);
        $payloadJson = $this->decodeBase64Url($encodedPayload);
        $signature = $this->decodeBase64Url($encodedSignature);

        if ($headerJson === null || $payloadJson === null || $signature === null) {
            return null;
        }

        $header = json_decode($headerJson, true);
        $claims = json_decode($payloadJson, true);

        if (! is_array($header) || ! is_array($claims)) {
            return null;
        }

        return [
            'header' => $header,
            'claims' => $claims,
            'signed_part' => $encodedHeader.'.'.$encodedPayload,
            'signature' => $signature,
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function isSupportedHeader(array $header): bool
    {
        return ($header['alg'] ?? null) === 'RS256';
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function hasValidClaims(array $claims): bool
    {
        $issuer = config('services.keycloak.issuer');
        $clientId = config('services.keycloak.client_id');

        if (! is_string($issuer) || $issuer === '' || ! is_string($clientId) || $clientId === '') {
            return false;
        }

        if (($claims['iss'] ?? null) !== $issuer) {
            return false;
        }

        $audience = Arr::wrap($claims['aud'] ?? []);
        $authorizedParty = $claims['azp'] ?? null;

        if (! in_array($clientId, $audience, true) && $authorizedParty !== $clientId) {
            return false;
        }

        $now = now()->timestamp;
        $expiresAt = $claims['exp'] ?? null;
        $notBefore = $claims['nbf'] ?? null;

        if (! is_int($expiresAt) || $expiresAt < $now) {
            return false;
        }

        if ($notBefore !== null && (! is_int($notBefore) || $notBefore > $now)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function hasValidSignature(array $header, string $signedPart, string $signature): bool
    {
        $kid = $header['kid'] ?? null;

        $publicKey = $this->keycloakJwksPublicKeyResolver->resolve(
            is_string($kid) ? $kid : null,
        );

        if ($publicKey === null) {
            $publicKey = $this->normalizePublicKey(config('services.keycloak.public_key'));
        }

        if ($publicKey === null) {
            return false;
        }

        $result = openssl_verify($signedPart, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    private function normalizePublicKey(mixed $configuredPublicKey): ?string
    {
        if (! is_string($configuredPublicKey) || trim($configuredPublicKey) === '') {
            return null;
        }

        $publicKey = trim($configuredPublicKey);

        if (str_contains($publicKey, 'BEGIN PUBLIC KEY')) {
            return $publicKey;
        }

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split($publicKey, 64, "\n")
            ."-----END PUBLIC KEY-----";
    }

    private function decodeBase64Url(string $encoded): ?string
    {
        $remainder = strlen($encoded) % 4;

        if ($remainder > 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
