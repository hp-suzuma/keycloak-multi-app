<?php

namespace App\Services\Playbook;

use App\Models\Playbook;
use App\Services\Auth\CurrentUser;
use App\Services\Resource\AuthorizedScopedResourceService;

class FindAuthorizedPlaybook
{
    public function __construct(
        private readonly AuthorizedScopedResourceService $authorizedScopedResourceService,
    ) {
    }

    public function resolve(?CurrentUser $currentUser, int $playbookId, string $requiredPermission): Playbook
    {
        /** @var Playbook $playbook */
        $playbook = $this->authorizedScopedResourceService
            ->find(Playbook::class, $currentUser, $playbookId, $requiredPermission);

        return $playbook;
    }
}
