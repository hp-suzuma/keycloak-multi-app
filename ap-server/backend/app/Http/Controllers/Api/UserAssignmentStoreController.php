<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserAssignmentRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\User\UserAssignmentStoreService;
use Illuminate\Http\JsonResponse;

class UserAssignmentStoreController extends Controller
{
    public function __invoke(
        string $keycloakSub,
        UserAssignmentRequest $request,
        UserAssignmentStoreService $userAssignmentStoreService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $userAssignmentStoreService->buildResponse(
                $currentUserResolver->resolve(),
                $keycloakSub,
                $request->payload(),
            ),
            JsonResponse::HTTP_CREATED,
        );
    }
}
