<?php

namespace App\Services\User;

use App\Models\Scope;
use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FindManageableScope
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    public function resolve(?CurrentUser $currentUser, int $scopeId): Scope
    {
        $isVisible = in_array(
            $scopeId,
            $this->authorizationService->accessibleScopeIds($currentUser, ['user.manage']),
            true,
        );

        if (! $isVisible) {
            throw new HttpResponseException(response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND));
        }

        return Scope::query()->findOrFail($scopeId);
    }
}
