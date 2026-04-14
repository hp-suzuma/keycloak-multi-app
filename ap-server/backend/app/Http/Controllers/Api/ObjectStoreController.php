<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Object\ObjectStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObjectStoreController extends Controller
{
    public function __invoke(
        Request $request,
        ObjectStoreService $objectStoreService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $validated = $request->validate([
            'scope_id' => ['required', 'integer', 'exists:scopes,id'],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(
            $objectStoreService->buildResponse(
                $currentUserResolver->resolve(),
                $validated,
            ),
            JsonResponse::HTTP_CREATED,
        );
    }
}
