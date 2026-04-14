<?php

namespace App\Services\Playbook;

use App\Models\Playbook;

class PlaybookPayload
{
    /**
     * @return array{id: int, scope_id: int, code: string, name: string}
     */
    public static function fromModel(Playbook $playbook): array
    {
        return [
            'id' => $playbook->id,
            'scope_id' => $playbook->scope_id,
            'code' => $playbook->code,
            'name' => $playbook->name,
        ];
    }
}
