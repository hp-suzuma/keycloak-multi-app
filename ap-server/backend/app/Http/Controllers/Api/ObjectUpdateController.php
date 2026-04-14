<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Object\ObjectUpdateService;
use Illuminate\Http\JsonResponse;

class ObjectUpdateController extends Controller
{
    public function __invoke(
        UpdateScopedResourceRequest $request,
        int $objectId,
        ObjectUpdateService $objectUpdateService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $objectUpdateService->buildResponse(
                $currentUserResolver->resolve(),
                $objectId,
                $request->validated(),
            ),
        );
    }
}
