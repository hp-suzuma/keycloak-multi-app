<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthService $healthService): JsonResponse
    {
        return response()->json($healthService->buildResponse());
    }
}
