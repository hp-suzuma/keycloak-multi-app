<?php

namespace App\Models\Concerns;

use App\Support\AuditActor;

trait HasAuditColumns
{
    protected function applyAuditColumnsForInsert(): void
    {
        $actor = AuditActor::name();

        $this->setAttribute('created_by', $this->getAttribute('created_by') ?? $actor);
        $this->setAttribute('updated_by', $actor);
    }

    protected function applyAuditColumnsForUpdate(): void
    {
        $this->setAttribute('updated_by', AuditActor::name());
    }
}
