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
        $keycloakUser = $this->keycloakTokenCurrentUserResolver
            ->resolveFromBearerToken(request()->bearerToken());

        if ($keycloakUser !== null) {
            return $keycloakUser;
        }

        $user = request()->user();

        return $user instanceof User ? CurrentUser::fromUser($user) : null;
    }
}
