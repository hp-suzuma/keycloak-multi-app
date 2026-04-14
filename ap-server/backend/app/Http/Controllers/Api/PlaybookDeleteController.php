<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Playbook\PlaybookDeleteService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PlaybookDeleteController extends Controller
{
    public function __invoke(
        int $playbookId,
        PlaybookDeleteService $playbookDeleteService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $playbookDeleteService->delete($currentUserResolver->resolve(), $playbookId);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
