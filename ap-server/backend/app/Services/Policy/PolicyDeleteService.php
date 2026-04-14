<?php

namespace App\Services\Policy;

use App\Services\Auth\CurrentUser;

class PolicyDeleteService
{
    public function __construct(
        private readonly FindAuthorizedPolicy $findAuthorizedPolicy,
    ) {
    }

    public function delete(?CurrentUser $currentUser, int $policyId): void
    {
        $policy = $this->findAuthorizedPolicy
            ->resolve($currentUser, $policyId, 'object.delete');

        $policy->delete();
    }
}
