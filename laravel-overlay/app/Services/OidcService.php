<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OidcService
{
    public function authorizationUrl(
        string $clientId,
        string $redirectUri,
        string $state,
        string $nonce,
        ?string $prompt = null,
    ): string {
        $query = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($prompt) {
            $query['prompt'] = $prompt;
        }

        return $this->publicBase().'/protocol/openid-connect/auth?'.http_build_query($query);
    }

    public function exchangeCode(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $code,
    ): array {
        return Http::asForm()->post($this->internalBase().'/protocol/openid-connect/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ])->throw()->json();
    }

    public function userInfo(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->internalBase().'/protocol/openid-connect/userinfo')
            ->throw()
            ->json();
    }

    public function decodeIdToken(?string $idToken): array
    {
        abort_unless($idToken, 500, 'id_token is missing.');

        $parts = explode('.', $idToken);
        abort_unless(count($parts) === 3, 500, 'Invalid id_token.');

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        abort_unless(is_array($payload), 500, 'Unable to decode id_token payload.');

        return $payload;
    }

    private function publicBase(): string
    {
        return rtrim(config('services.keycloak.issuer'), '/');
    }

    private function internalBase(): string
    {
        return rtrim(config('services.keycloak.internal_base_url'), '/');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
