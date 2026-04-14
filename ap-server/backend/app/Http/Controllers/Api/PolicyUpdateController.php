<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Policy\PolicyUpdateService;
use Illuminate\Http\JsonResponse;

class PolicyUpdateController extends Controller
{
    public function __invoke(
        UpdateScopedResourceRequest $request,
        int $policyId,
        PolicyUpdateService $policyUpdateService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $policyUpdateService->buildResponse(
                $currentUserResolver->resolve(),
                $policyId,
                $request->validated(),
            ),
        );
    }
}
