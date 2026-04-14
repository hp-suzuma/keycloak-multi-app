<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Object\ObjectShowService;
use Illuminate\Http\JsonResponse;

class ObjectShowController extends Controller
{
    public function __invoke(
        int $objectId,
        ObjectShowService $objectShowService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        return response()->json(
            $objectShowService->buildResponse($currentUserResolver->resolve(), $objectId),
        );
    }
}
