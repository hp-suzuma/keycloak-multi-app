<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Checklist\ChecklistDeleteService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChecklistDeleteController extends Controller
{
    public function __invoke(
        int $checklistId,
        ChecklistDeleteService $checklistDeleteService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $checklistDeleteService->delete($currentUserResolver->resolve(), $checklistId);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
