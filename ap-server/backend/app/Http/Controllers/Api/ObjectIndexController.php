<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Object\ObjectIndexService;
use Illuminate\Http\JsonResponse;

class ObjectIndexController extends Controller
{
    public function __invoke(
        IndexScopedResourceRequest $request,
        ObjectIndexService $objectIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse
    {
        return response()->json(
            $objectIndexService->buildResponse($currentUserResolver->resolve(), $request->filters()),
        );
    }
}
