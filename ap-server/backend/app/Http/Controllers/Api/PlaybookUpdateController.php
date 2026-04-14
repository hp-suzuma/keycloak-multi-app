<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Playbook\PlaybookUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybookUpdateController extends Controller
{
    public function __invoke(
        Request $request,
        int $playbookId,
        PlaybookUpdateService $playbookUpdateService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $validated = $request->validate([
            'scope_id' => ['sometimes', 'integer', 'exists:scopes,id'],
            'code' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        return response()->json(
            $playbookUpdateService->buildResponse(
                $currentUserResolver->resolve(),
                $playbookId,
                $validated,
            ),
        );
    }
}
