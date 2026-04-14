<?php

namespace App\Services\Object;

use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Auth\CurrentUser;

class ObjectUpdateService
{
    public function __construct(
        private readonly FindAuthorizedObject $findAuthorizedObject,
        private readonly EnsureAuthorizedScope $ensureAuthorizedScope,
        private readonly EnsureUniqueObjectCode $ensureUniqueObjectCode,
        private readonly ObjectCodeNormalizer $objectCodeNormalizer,
    ) {
    }

    /**
     * @param  array{scope_id?: int, code?: string, name?: string}  $attributes
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $objectId, array $attributes): array
    {
        if ($attributes === []) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'payload' => ['At least one of scope_id, code, or name is required.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $managedObject = $this->findAuthorizedObject
            ->resolve($currentUser, $objectId, 'object.update');

        if (isset($attributes['scope_id']) && $attributes['scope_id'] !== $managedObject->scope_id) {
            $this->ensureAuthorizedScope->ensure($currentUser, $attributes['scope_id'], 'object.create');
        }

        $nextScopeId = $attributes['scope_id'] ?? $managedObject->scope_id;
        $nextCode = isset($attributes['code'])
            ? $this->objectCodeNormalizer->normalize($attributes['code'])
            : $managedObject->code;

        if ($nextScopeId !== $managedObject->scope_id || $nextCode !== $managedObject->code) {
            $this->ensureUniqueObjectCode->ensure($nextScopeId, $nextCode, $managedObject->id);
        }

        $managedObject->fill([
            'scope_id' => $nextScopeId,
            'code' => $nextCode,
            'name' => $attributes['name'] ?? $managedObject->name,
        ]);
        $managedObject->save();

        return [
            'data' => ObjectPayload::fromModel($managedObject->fresh()),
        ];
    }
}
