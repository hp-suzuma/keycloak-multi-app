<?php

namespace App\Services\User;

use App\Models\UserRoleAssignment;
use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FindVisibleAssignment
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    public function resolve(?CurrentUser $currentUser, string $keycloakSub, int $scopeId, int $roleId): UserRoleAssignment
    {
        $assignment = UserRoleAssignment::query()
            ->where('keycloak_sub', $keycloakSub)
            ->where('scope_id', $scopeId)
            ->where('role_id', $roleId)
            ->whereIn(
                'scope_id',
                $this->authorizationService->accessibleScopeIds($currentUser, ['user.manage']),
            )
            ->first();

        if ($assignment === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND));
        }

        return $assignment;
    }

    public function resolveById(?CurrentUser $currentUser, string $keycloakSub, int $assignmentId): UserRoleAssignment
    {
        $assignment = UserRoleAssignment::query()
            ->where('id', $assignmentId)
            ->where('keycloak_sub', $keycloakSub)
            ->whereIn(
                'scope_id',
                $this->authorizationService->accessibleScopeIds($currentUser, ['user.manage']),
            )
            ->first();

        if ($assignment === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND));
        }

        return $assignment;
    }
}
