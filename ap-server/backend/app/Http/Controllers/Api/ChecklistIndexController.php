<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChecklistIndexRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Checklist\ChecklistIndexService;
use Illuminate\Http\JsonResponse;

class ChecklistIndexController extends Controller
{
    public function __invoke(
        ChecklistIndexRequest $request,
        ChecklistIndexService $checklistIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $checklistIndexService->buildResponse($currentUserResolver->resolve(), $request->filters()),
        );
    }
}
