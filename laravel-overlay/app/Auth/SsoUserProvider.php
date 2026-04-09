<?php

namespace App\Auth;

use App\Models\SsoUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Cache;

class SsoUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $payload = Cache::get($this->cacheKey($identifier));

        return $payload ? SsoUser::fromClaims($payload) : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->retrieveById($identifier);
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $sub = $credentials['sub'] ?? null;

        if (! $sub) {
            return null;
        }

        return $this->retrieveById($sub);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return true;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
    }

    public function storeUser(SsoUser $user, int $seconds = 7200): void
    {
        Cache::put($this->cacheKey($user->getAuthIdentifier()), $user->toArray(), $seconds);
    }

    private function cacheKey(string $identifier): string
    {
        return 'sso-user:'.$identifier;
    }
}
