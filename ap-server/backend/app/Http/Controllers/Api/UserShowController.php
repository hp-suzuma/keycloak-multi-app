<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\User\UserShowService;
use Illuminate\Http\JsonResponse;

class UserShowController extends Controller
{
    public function __invoke(
        string $keycloakSub,
        UserShowService $userShowService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $userShowService->buildResponse($currentUserResolver->resolve(), $keycloakSub),
        );
    }
}
