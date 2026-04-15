<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RoleIndexRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Role\RoleIndexService;
use Illuminate\Http\JsonResponse;

class RoleIndexController extends Controller
{
    public function __invoke(
        RoleIndexRequest $request,
        RoleIndexService $roleIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $roleIndexService->buildResponse($currentUserResolver->resolve(), $request->filters()),
        );
    }
}
