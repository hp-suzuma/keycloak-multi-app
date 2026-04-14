<?php

namespace App\Services\Resource;

use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class AuthorizedScopedResourceService
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function find(string $modelClass, ?CurrentUser $currentUser, int $resourceId, string $requiredPermission): Model
    {
        $resource = $modelClass::query()->find($resourceId);

        if ($resource === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND));
        }

        $scopeId = $resource->getAttribute('scope_id');

        if (! is_int($scopeId) || ! $this->authorizationService->canAccessScope($currentUser, $requiredPermission, $scopeId)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Forbidden',
                'required_permissions' => [$requiredPermission],
                'scope_id' => $scopeId,
            ], Response::HTTP_FORBIDDEN));
        }

        return $resource;
    }
}
