<?php

namespace App\Services\Scope;

use App\Models\Scope;

class ScopePayload
{
    /**
     * @return array{
     *     id: int,
     *     layer: string,
     *     code: string,
     *     name: string,
     *     parent_scope_id: int|null
     * }
     */
    public static function fromModel(Scope $scope): array
    {
        return [
            'id' => $scope->id,
            'layer' => $scope->layer,
            'code' => $scope->code,
            'name' => $scope->name,
            'parent_scope_id' => $scope->parent_scope_id,
        ];
    }
}
