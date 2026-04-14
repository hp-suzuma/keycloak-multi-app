<?php

namespace App\Services\Object;

use App\Models\ManagedObject;
use App\Services\Auth\CurrentUser;
use App\Services\Resource\AuthorizedScopedResourceService;

class FindAuthorizedObject
{
    public function __construct(
        private readonly AuthorizedScopedResourceService $authorizedScopedResourceService,
    ) {
    }

    public function resolve(?CurrentUser $currentUser, int $objectId, string $requiredPermission): ManagedObject
    {
        /** @var ManagedObject $managedObject */
        $managedObject = $this->authorizedScopedResourceService
            ->find(ManagedObject::class, $currentUser, $objectId, $requiredPermission);

        return $managedObject;
    }
}
