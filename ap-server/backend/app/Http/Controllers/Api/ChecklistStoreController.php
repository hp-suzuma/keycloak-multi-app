<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Checklist\ChecklistStoreService;
use Illuminate\Http\JsonResponse;

class ChecklistStoreController extends Controller
{
    public function __invoke(
        StoreScopedResourceRequest $request,
        ChecklistStoreService $checklistStoreService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $checklistStoreService->buildResponse($currentUserResolver->resolve(), $request->validated()),
            JsonResponse::HTTP_CREATED,
        );
    }
}
