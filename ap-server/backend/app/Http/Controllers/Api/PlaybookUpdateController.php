<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Playbook\PlaybookUpdateService;
use Illuminate\Http\JsonResponse;

class PlaybookUpdateController extends Controller
{
    public function __invoke(
        UpdateScopedResourceRequest $request,
        int $playbookId,
        PlaybookUpdateService $playbookUpdateService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $playbookUpdateService->buildResponse(
                $currentUserResolver->resolve(),
                $playbookId,
                $request->validated(),
            ),
        );
    }
}
