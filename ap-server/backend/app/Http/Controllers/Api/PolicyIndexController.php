<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PolicyIndexRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Policy\PolicyIndexService;
use Illuminate\Http\JsonResponse;

class PolicyIndexController extends Controller
{
    public function __invoke(
        PolicyIndexRequest $request,
        PolicyIndexService $policyIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $policyIndexService->buildResponse($currentUserResolver->resolve(), $request->filters()),
        );
    }
}
