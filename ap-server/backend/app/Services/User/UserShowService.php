<?php

namespace App\Services\User;

use App\Services\Auth\CurrentUser;

class UserShowService
{
    public function __construct(
        private readonly FindVisibleUser $findVisibleUser,
    ) {
    }

    /**
     * @return array{
     *     data: array{
     *         keycloak_sub: string,
     *         display_name: string|null,
     *         email: string|null,
     *         assignments: array<int, array{
     *             scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *             role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *             permissions: array<int, array{id: int, slug: string, name: string}>
     *         }>,
     *         permissions: array<int, string>
     *     }
     * }
     */
    public function buildResponse(?CurrentUser $currentUser, string $keycloakSub): array
    {
        $user = $this->findVisibleUser->resolve($currentUser, $keycloakSub);

        return [
            'data' => UserPayload::fromModel($user),
        ];
    }
}
