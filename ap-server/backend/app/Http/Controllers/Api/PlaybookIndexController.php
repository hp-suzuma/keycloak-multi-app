<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaybookIndexRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Playbook\PlaybookIndexService;
use Illuminate\Http\JsonResponse;

class PlaybookIndexController extends Controller
{
    public function __invoke(
        PlaybookIndexRequest $request,
        PlaybookIndexService $playbookIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $playbookIndexService->buildResponse($currentUserResolver->resolve(), $request->filters()),
        );
    }
}
