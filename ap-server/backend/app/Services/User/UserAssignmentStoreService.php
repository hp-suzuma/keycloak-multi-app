<?php

namespace App\Services\User;

use App\Models\UserRoleAssignment;
use App\Services\Auth\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UserAssignmentStoreService
{
    public function __construct(
        private readonly FindExistingUser $findExistingUser,
        private readonly FindManageableScope $findManageableScope,
        private readonly EnsureAssignmentRoleMatchesScope $ensureAssignmentRoleMatchesScope,
        private readonly FindVisibleUser $findVisibleUser,
    ) {
    }

    /**
     * @param  array{scope_id: int, role_id: int}  $attributes
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
    public function buildResponse(?CurrentUser $currentUser, string $keycloakSub, array $attributes): array
    {
        $user = $this->findExistingUser->resolve($keycloakSub);
        $scope = $this->findManageableScope->resolve($currentUser, $attributes['scope_id']);
        $role = $this->ensureAssignmentRoleMatchesScope->resolve($scope, $attributes['role_id']);

        $exists = UserRoleAssignment::query()
            ->where('keycloak_sub', $user->keycloak_sub)
            ->where('scope_id', $scope->id)
            ->where('role_id', $role->id)
            ->exists();

        if ($exists) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'assignment' => ['The assignment already exists.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        UserRoleAssignment::query()->create([
            'keycloak_sub' => $user->keycloak_sub,
            'scope_id' => $scope->id,
            'role_id' => $role->id,
        ]);

        return [
            'data' => UserPayload::fromModel(
                $this->findVisibleUser->resolve($currentUser, $user->keycloak_sub),
            ),
        ];
    }
}
