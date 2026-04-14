<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\Policy\PolicyIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PolicyIndexController extends Controller
{
    public function __invoke(
        Request $request,
        PolicyIndexService $policyIndexService,
        CurrentUserResolver $currentUserResolver,
    ): JsonResponse {
        $validated = $request->validate([
            'scope_id' => ['nullable', 'integer', 'exists:scopes,id'],
            'code' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['id', '-id', 'code', '-code', 'name', '-name'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (isset($validated['scope_id'])) {
            $validated['scope_id'] = (int) $validated['scope_id'];
        }

        if (isset($validated['page'])) {
            $validated['page'] = (int) $validated['page'];
        }

        if (isset($validated['per_page'])) {
            $validated['per_page'] = (int) $validated['per_page'];
        }

        return response()->json(
            $policyIndexService->buildResponse($currentUserResolver->resolve(), $validated),
        );
    }
}
