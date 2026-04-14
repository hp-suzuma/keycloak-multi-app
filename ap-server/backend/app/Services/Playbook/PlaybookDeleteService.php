<?php

namespace App\Services\Playbook;

use App\Services\Auth\CurrentUser;

class PlaybookDeleteService
{
    public function __construct(
        private readonly FindAuthorizedPlaybook $findAuthorizedPlaybook,
    ) {
    }

    public function delete(?CurrentUser $currentUser, int $playbookId): void
    {
        $playbook = $this->findAuthorizedPlaybook
            ->resolve($currentUser, $playbookId, 'object.delete');

        $playbook->delete();
    }
}
