<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateScopedResourceRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Checklist\ChecklistUpdateService;
use Illuminate\Http\JsonResponse;

class ChecklistUpdateController extends Controller
{
    public function __invoke(
        UpdateScopedResourceRequest $request,
        int $checklistId,
        ChecklistUpdateService $checklistUpdateService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $checklistUpdateService->buildResponse(
                $currentUserResolver->resolve(),
                $checklistId,
                $request->validated(),
            ),
        );
    }
}
