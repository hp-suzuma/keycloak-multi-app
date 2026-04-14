<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Policy\PolicyStoreService;
use Illuminate\Http\JsonResponse;

class PolicyStoreController extends Controller
{
    public function __invoke(
        StoreScopedResourceRequest $request,
        PolicyStoreService $policyStoreService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $policyStoreService->buildResponse($currentUserResolver->resolve(), $request->validated()),
            JsonResponse::HTTP_CREATED,
        );
    }
}
