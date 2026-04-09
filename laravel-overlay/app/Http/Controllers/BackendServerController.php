<?php

namespace App\Http\Controllers;

use App\Models\RouteAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BackendServerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RouteAssignment::query()
                ->orderBy('priority')
                ->orderBy('sub')
                ->get(),
        ]);
    }

    public function show(string $sub): JsonResponse
    {
        $assignment = RouteAssignment::query()->firstWhere('sub', $sub);

        if (! $assignment) {
            $assignment = RouteAssignment::query()->create($this->defaultAssignment($sub));
        }

        if (! $assignment->is_active) {
            return response()->json([
                'message' => 'Assignment is inactive.',
                'sub' => $sub,
            ], 404);
        }

        $assignment->forceFill([
            'last_resolved_at' => Carbon::now(),
        ])->save();

        return response()->json($this->serializeAssignment($assignment));
    }

    public function store(Request $request): JsonResponse
    {
        $assignment = RouteAssignment::query()->create($this->validated($request));

        return response()->json($this->serializeAssignment($assignment), 201);
    }

    public function update(Request $request, string $sub): JsonResponse
    {
        $assignment = RouteAssignment::query()->where('sub', $sub)->firstOrFail();
        $assignment->fill($this->validated($request, true))->save();

        return response()->json($this->serializeAssignment($assignment->fresh()));
    }

    public function destroy(string $sub): JsonResponse
    {
        $assignment = RouteAssignment::query()->where('sub', $sub)->firstOrFail();
        $assignment->delete();

        return response()->noContent();
    }

    private function defaultAssignment(string $sub): array
    {
        $siteCode = str_ends_with(strtolower($sub), 'b') ? 'B' : 'A';

        return [
            'sub' => $sub,
            'display_name' => 'Auto assigned '.strtoupper($siteCode),
            'site_code' => $siteCode,
            'server_url' => sprintf('https://%s.example.com', strtolower($siteCode)),
            'is_active' => true,
            'priority' => 100,
            'notes' => 'Auto-created from fallback assignment rule.',
        ];
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'sub' => $partial
                ? ['prohibited']
                : ['required', 'string', 'max:255', 'unique:route_assignments,sub'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_code' => [$required, 'string', 'max:16'],
            'server_url' => [$required, 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:999999'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    private function serializeAssignment(RouteAssignment $assignment): array
    {
        return [
            'sub' => $assignment->sub,
            'display_name' => $assignment->display_name,
            'site_code' => $assignment->site_code,
            'server_url' => $assignment->server_url,
            'is_active' => $assignment->is_active,
            'priority' => $assignment->priority,
            'notes' => $assignment->notes,
            'last_resolved_at' => optional($assignment->last_resolved_at)?->toAtomString(),
            'updated_at' => optional($assignment->updated_at)?->toAtomString(),
            'created_at' => optional($assignment->created_at)?->toAtomString(),
        ];
    }
}
