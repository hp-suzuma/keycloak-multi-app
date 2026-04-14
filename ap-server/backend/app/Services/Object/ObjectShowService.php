<?php

namespace App\Services\Object;

use App\Services\Auth\CurrentUser;

class ObjectShowService
{
    public function __construct(
        private readonly FindAuthorizedObject $findAuthorizedObject,
    ) {
    }

    /**
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $objectId): array
    {
        $managedObject = $this->findAuthorizedObject
            ->resolve($currentUser, $objectId, 'object.read');

        return [
            'data' => ObjectPayload::fromModel($managedObject),
        ];
    }
}
