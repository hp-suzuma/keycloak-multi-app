<?php

namespace App\Services\Playbook;

use App\Services\Auth\CurrentUser;

class PlaybookShowService
{
    public function __construct(
        private readonly FindAuthorizedPlaybook $findAuthorizedPlaybook,
    ) {
    }

    /**
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $playbookId): array
    {
        $playbook = $this->findAuthorizedPlaybook
            ->resolve($currentUser, $playbookId, 'object.read');

        return [
            'data' => PlaybookPayload::fromModel($playbook),
        ];
    }
}
