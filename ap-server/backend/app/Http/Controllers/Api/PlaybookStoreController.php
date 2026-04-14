<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Playbook\PlaybookStoreService;
use Illuminate\Http\JsonResponse;

class PlaybookStoreController extends Controller
{
    public function __invoke(
        StoreScopedResourceRequest $request,
        PlaybookStoreService $playbookStoreService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $playbookStoreService->buildResponse(
                $currentUserResolver->resolve(),
                $request->validated(),
            ),
            JsonResponse::HTTP_CREATED,
        );
    }
}
