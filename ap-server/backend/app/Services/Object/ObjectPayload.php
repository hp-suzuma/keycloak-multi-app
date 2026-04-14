<?php

namespace App\Services\Object;

use App\Models\ManagedObject;

class ObjectPayload
{
    /**
     * @return array{id: int, scope_id: int, code: string, name: string}
     */
    public static function fromModel(ManagedObject $managedObject): array
    {
        return [
            'id' => $managedObject->id,
            'scope_id' => $managedObject->scope_id,
            'code' => $managedObject->code,
            'name' => $managedObject->name,
        ];
    }
}
