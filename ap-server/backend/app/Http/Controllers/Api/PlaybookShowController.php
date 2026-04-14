<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Playbook\PlaybookShowService;
use Illuminate\Http\JsonResponse;

class PlaybookShowController extends Controller
{
    public function __invoke(
        int $playbookId,
        PlaybookShowService $playbookShowService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $playbookShowService->buildResponse($currentUserResolver->resolve(), $playbookId),
        );
    }
}
