<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Checklist\ChecklistShowService;
use Illuminate\Http\JsonResponse;

class ChecklistShowController extends Controller
{
    public function __invoke(
        int $checklistId,
        ChecklistShowService $checklistShowService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $checklistShowService->buildResponse($currentUserResolver->resolve(), $checklistId),
        );
    }
}
