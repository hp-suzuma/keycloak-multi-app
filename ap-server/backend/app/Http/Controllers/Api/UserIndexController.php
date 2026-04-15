<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserIndexRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\User\UserIndexService;
use Illuminate\Http\JsonResponse;

class UserIndexController extends Controller
{
    public function __invoke(
        UserIndexRequest $request,
        UserIndexService $userIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $userIndexService->buildResponse($currentUserResolver->resolve(), $request->filters()),
        );
    }
}
