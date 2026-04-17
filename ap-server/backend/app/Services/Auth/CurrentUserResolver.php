<?php

namespace App\Services\Auth;

use App\Models\User;

class CurrentUserResolver
{
    public function __construct(
        private readonly KeycloakTokenCurrentUserResolver $keycloakTokenCurrentUserResolver,
    ) {
    }

    public function resolve(): ?CurrentUser
    {
        $token = $this->bearerToken();
        $keycloakUser = $this->keycloakTokenCurrentUserResolver
            ->resolveFromBearerToken($token);

        if ($keycloakUser !== null) {
            return $keycloakUser;
        }

        $user = request()->user();

        return $user instanceof User ? CurrentUser::fromUser($user) : null;
    }

    private function bearerToken(): ?string
    {
        $request = request();
        $queryToken = $request->query('access_token');

        if (is_string($queryToken) && $queryToken !== '') {
            return $queryToken;
        }

        $candidateValues = [
            $request->bearerToken(),
            $request->header('Authorization'),
            $request->header('X-Forwarded-Authorization'),
        ];

        foreach ([
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
            'AUTHORIZATION',
            'HTTP_X_FORWARDED_AUTHORIZATION',
        ] as $serverKey) {
            $candidateValues[] = $request->server($serverKey);
        }

        if (function_exists('getallheaders')) {
            $candidateValues[] = getallheaders()['Authorization'] ?? getallheaders()['authorization'] ?? null;
            $candidateValues[] = getallheaders()['X-Forwarded-Authorization'] ?? getallheaders()['x-forwarded-authorization'] ?? null;
        }

        if (function_exists('apache_request_headers')) {
            $candidateValues[] = apache_request_headers()['Authorization'] ?? apache_request_headers()['authorization'] ?? null;
            $candidateValues[] = apache_request_headers()['X-Forwarded-Authorization'] ?? apache_request_headers()['x-forwarded-authorization'] ?? null;
        }

        foreach ($candidateValues as $headerValue) {
            $token = $this->extractBearerToken($headerValue);

            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    private function extractBearerToken(mixed $headerValue): ?string
    {
        if (! is_string($headerValue) || ! str_starts_with($headerValue, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($headerValue, 7));

        return $token !== '' ? $token : null;
    }
}
