<?php

namespace App\Services\Object;

use App\Models\Scope;
use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthorizedScope
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    public function ensure(?CurrentUser $currentUser, int $scopeId, string $requiredPermission): Scope
    {
        $scope = Scope::query()->find($scopeId);

        if ($scope === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND));
        }

        if (! $this->authorizationService->canAccessScope($currentUser, $requiredPermission, $scopeId)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Forbidden',
                'required_permissions' => [$requiredPermission],
                'scope_id' => $scopeId,
            ], Response::HTTP_FORBIDDEN));
        }

        return $scope;
    }
}
