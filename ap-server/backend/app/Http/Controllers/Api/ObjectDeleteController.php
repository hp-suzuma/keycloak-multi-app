<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Object\ObjectDeleteService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ObjectDeleteController extends Controller
{
    public function __invoke(
        int $objectId,
        ObjectDeleteService $objectDeleteService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $objectDeleteService->delete($currentUserResolver->resolve(), $objectId);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
