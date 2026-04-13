<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Auth\MeService;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __invoke(
        MeService $meService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $meService->buildResponse($currentUserResolver->resolve()),
        );
    }
}
