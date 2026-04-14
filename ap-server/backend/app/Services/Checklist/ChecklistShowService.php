<?php

namespace App\Services\Checklist;

use App\Services\Auth\CurrentUser;

class ChecklistShowService
{
    public function __construct(
        private readonly FindAuthorizedChecklist $findAuthorizedChecklist,
    ) {
    }

    /**
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $checklistId): array
    {
        $checklist = $this->findAuthorizedChecklist
            ->resolve($currentUser, $checklistId, 'object.read');

        return [
            'data' => ChecklistPayload::fromModel($checklist),
        ];
    }
}
