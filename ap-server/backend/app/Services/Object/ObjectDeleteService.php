<?php

namespace App\Services\Object;

use App\Services\Auth\CurrentUser;

class ObjectDeleteService
{
    public function __construct(
        private readonly FindAuthorizedObject $findAuthorizedObject,
    ) {
    }

    public function delete(?CurrentUser $currentUser, int $objectId): void
    {
        $managedObject = $this->findAuthorizedObject
            ->resolve($currentUser, $objectId, 'object.delete');

        $managedObject->delete();
    }
}
