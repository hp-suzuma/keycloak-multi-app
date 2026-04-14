<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Playbook\PlaybookStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybookStoreController extends Controller
{
    public function __invoke(
        Request $request,
        PlaybookStoreService $playbookStoreService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $validated = $request->validate([
            'scope_id' => ['required', 'integer', 'exists:scopes,id'],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(
            $playbookStoreService->buildResponse(
                $currentUserResolver->resolve(),
                $validated,
            ),
            JsonResponse::HTTP_CREATED,
        );
    }
}
