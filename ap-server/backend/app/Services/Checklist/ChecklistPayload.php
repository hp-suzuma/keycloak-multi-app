<?php

namespace App\Services\Checklist;

use App\Models\Checklist;

class ChecklistPayload
{
    /**
     * @return array{id: int, scope_id: int, code: string, name: string}
     */
    public static function fromModel(Checklist $checklist): array
    {
        return [
            'id' => $checklist->id,
            'scope_id' => $checklist->scope_id,
            'code' => $checklist->code,
            'name' => $checklist->name,
        ];
    }
}
