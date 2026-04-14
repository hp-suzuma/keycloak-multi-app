<?php

namespace App\Services\Checklist;

use App\Services\Auth\CurrentUser;
use App\Services\Object\EnsureAuthorizedScope;
use App\Services\Object\ObjectCodeNormalizer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ChecklistUpdateService
{
    public function __construct(
        private readonly FindAuthorizedChecklist $findAuthorizedChecklist,
        private readonly EnsureAuthorizedScope $ensureAuthorizedScope,
        private readonly EnsureUniqueChecklistCode $ensureUniqueChecklistCode,
        private readonly ObjectCodeNormalizer $objectCodeNormalizer,
    ) {
    }

    /**
     * @param  array{scope_id?: int, code?: string, name?: string}  $attributes
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $checklistId, array $attributes): array
    {
        if ($attributes === []) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'payload' => ['At least one of scope_id, code, or name is required.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $checklist = $this->findAuthorizedChecklist
            ->resolve($currentUser, $checklistId, 'object.update');

        if (isset($attributes['scope_id']) && $attributes['scope_id'] !== $checklist->scope_id) {
            $this->ensureAuthorizedScope->ensure($currentUser, $attributes['scope_id'], 'object.create');
        }

        $nextScopeId = $attributes['scope_id'] ?? $checklist->scope_id;
        $nextCode = isset($attributes['code'])
            ? $this->objectCodeNormalizer->normalize($attributes['code'])
            : $checklist->code;

        if ($nextScopeId !== $checklist->scope_id || $nextCode !== $checklist->code) {
            $this->ensureUniqueChecklistCode->ensure($nextScopeId, $nextCode, $checklist->id);
        }

        $checklist->fill([
            'scope_id' => $nextScopeId,
            'code' => $nextCode,
            'name' => $attributes['name'] ?? $checklist->name,
        ]);
        $checklist->save();

        return [
            'data' => ChecklistPayload::fromModel($checklist->fresh()),
        ];
    }
}
