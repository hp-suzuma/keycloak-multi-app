<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Policy\PolicyShowService;
use Illuminate\Http\JsonResponse;

class PolicyShowController extends Controller
{
    public function __invoke(
        int $policyId,
        PolicyShowService $policyShowService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $policyShowService->buildResponse($currentUserResolver->resolve(), $policyId),
        );
    }
}
