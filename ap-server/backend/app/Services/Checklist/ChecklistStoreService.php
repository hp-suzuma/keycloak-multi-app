<?php

namespace App\Services\Checklist;

use App\Models\Checklist;
use App\Services\Auth\CurrentUser;
use App\Services\Object\EnsureAuthorizedScope;
use App\Services\Object\ObjectCodeNormalizer;

class ChecklistStoreService
{
    public function __construct(
        private readonly EnsureAuthorizedScope $ensureAuthorizedScope,
        private readonly EnsureUniqueChecklistCode $ensureUniqueChecklistCode,
        private readonly ObjectCodeNormalizer $objectCodeNormalizer,
    ) {
    }

    /**
     * @param  array{scope_id: int, code: string, name: string}  $attributes
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, array $attributes): array
    {
        $this->ensureAuthorizedScope->ensure($currentUser, $attributes['scope_id'], 'object.create');
        $normalizedCode = $this->objectCodeNormalizer->normalize($attributes['code']);
        $this->ensureUniqueChecklistCode->ensure($attributes['scope_id'], $normalizedCode);

        $checklist = Checklist::query()->create([
            'scope_id' => $attributes['scope_id'],
            'code' => $normalizedCode,
            'name' => $attributes['name'],
        ]);

        return [
            'data' => ChecklistPayload::fromModel($checklist),
        ];
    }
}
