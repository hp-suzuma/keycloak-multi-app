<?php

namespace App\Services\Policy;

use App\Models\Policy;
use App\Services\Auth\CurrentUser;
use App\Services\Resource\AuthorizedScopedResourceService;

class FindAuthorizedPolicy
{
    public function __construct(
        private readonly AuthorizedScopedResourceService $authorizedScopedResourceService,
    ) {
    }

    public function resolve(?CurrentUser $currentUser, int $policyId, string $requiredPermission): Policy
    {
        /** @var Policy $policy */
        $policy = $this->authorizedScopedResourceService
            ->find(Policy::class, $currentUser, $policyId, $requiredPermission);

        return $policy;
    }
}
