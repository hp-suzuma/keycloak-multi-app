<?php

namespace App\Services\Object;

use App\Models\ManagedObject;
use App\Services\Auth\CurrentUser;

class ObjectStoreService
{
    public function __construct(
        private readonly EnsureAuthorizedScope $ensureAuthorizedScope,
        private readonly EnsureUniqueObjectCode $ensureUniqueObjectCode,
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
        $this->ensureUniqueObjectCode->ensure($attributes['scope_id'], $normalizedCode);

        $managedObject = ManagedObject::query()->create([
            'scope_id' => $attributes['scope_id'],
            'code' => $normalizedCode,
            'name' => $attributes['name'],
        ]);

        return [
            'data' => ObjectPayload::fromModel($managedObject),
        ];
    }
}
