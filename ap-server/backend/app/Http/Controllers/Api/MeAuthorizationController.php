<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\JsonResponse;

class MeAuthorizationController extends Controller
{
    public function __invoke(
        AuthorizationService $authorizationService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $authorizationService->buildResponse($currentUserResolver->resolve()),
        );
    }
}
