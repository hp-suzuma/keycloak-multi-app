<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Object\ObjectUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObjectUpdateController extends Controller
{
    public function __invoke(
        Request $request,
        int $objectId,
        ObjectUpdateService $objectUpdateService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $validated = $request->validate([
            'scope_id' => ['sometimes', 'integer', 'exists:scopes,id'],
            'code' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        return response()->json(
            $objectUpdateService->buildResponse(
                $currentUserResolver->resolve(),
                $objectId,
                $validated,
            ),
        );
    }
}
