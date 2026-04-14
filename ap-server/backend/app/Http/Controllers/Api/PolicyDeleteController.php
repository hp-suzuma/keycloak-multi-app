<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Policy\PolicyDeleteService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PolicyDeleteController extends Controller
{
    public function __invoke(
        int $policyId,
        PolicyDeleteService $policyDeleteService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $policyDeleteService->delete($currentUserResolver->resolve(), $policyId);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
