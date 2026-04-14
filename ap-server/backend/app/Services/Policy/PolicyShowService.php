<?php

namespace App\Services\Policy;

use App\Services\Auth\CurrentUser;

class PolicyShowService
{
    public function __construct(
        private readonly FindAuthorizedPolicy $findAuthorizedPolicy,
    ) {
    }

    /**
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $policyId): array
    {
        $policy = $this->findAuthorizedPolicy
            ->resolve($currentUser, $policyId, 'object.read');

        return [
            'data' => PolicyPayload::fromModel($policy),
        ];
    }
}
