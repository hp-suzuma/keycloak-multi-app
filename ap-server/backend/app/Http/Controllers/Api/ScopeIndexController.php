<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ScopeIndexRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Scope\ScopeIndexService;
use Illuminate\Http\JsonResponse;

class ScopeIndexController extends Controller
{
    public function __invoke(
        ScopeIndexRequest $request,
        ScopeIndexService $scopeIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $scopeIndexService->buildResponse($currentUserResolver->resolve(), $request->filters()),
        );
    }
}
