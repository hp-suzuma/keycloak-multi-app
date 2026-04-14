<?php

namespace App\Services\Checklist;

use App\Models\Checklist;
use App\Services\Auth\CurrentUser;
use App\Services\Resource\AuthorizedScopedResourceService;

class FindAuthorizedChecklist
{
    public function __construct(
        private readonly AuthorizedScopedResourceService $authorizedScopedResourceService,
    ) {
    }

    public function resolve(?CurrentUser $currentUser, int $checklistId, string $requiredPermission): Checklist
    {
        /** @var Checklist $checklist */
        $checklist = $this->authorizedScopedResourceService
            ->find(Checklist::class, $currentUser, $checklistId, $requiredPermission);

        return $checklist;
    }
}
