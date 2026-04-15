<?php

namespace App\Services\User;

use App\Models\ApUser;
use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FindVisibleUser
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    public function resolve(?CurrentUser $currentUser, string $keycloakSub): ApUser
    {
        $visibleScopeIds = $this->authorizationService
            ->accessibleScopeIds($currentUser, ['user.manage']);

        $user = ApUser::query()
            ->whereKey($keycloakSub)
            ->with([
                'roleAssignments' => fn ($assignments) => $assignments
                    ->whereIn('scope_id', $visibleScopeIds)
                    ->with([
                        'scope',
                        'role.permissions',
                    ]),
            ])
            ->first();

        if ($user === null || $user->roleAssignments->isEmpty()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND));
        }

        return $user;
    }
}
