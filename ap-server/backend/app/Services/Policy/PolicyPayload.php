<?php

namespace App\Services\Policy;

use App\Models\Policy;

class PolicyPayload
{
    /**
     * @return array{id: int, scope_id: int, code: string, name: string}
     */
    public static function fromModel(Policy $policy): array
    {
        return [
            'id' => $policy->id,
            'scope_id' => $policy->scope_id,
            'code' => $policy->code,
            'name' => $policy->name,
        ];
    }
}
