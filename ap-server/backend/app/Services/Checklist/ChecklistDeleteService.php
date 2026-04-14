<?php

namespace App\Services\Checklist;

use App\Services\Auth\CurrentUser;

class ChecklistDeleteService
{
    public function __construct(
        private readonly FindAuthorizedChecklist $findAuthorizedChecklist,
    ) {
    }

    public function delete(?CurrentUser $currentUser, int $checklistId): void
    {
        $checklist = $this->findAuthorizedChecklist
            ->resolve($currentUser, $checklistId, 'object.delete');

        $checklist->delete();
    }
}
