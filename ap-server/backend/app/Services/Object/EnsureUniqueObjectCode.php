<?php

namespace App\Services\Object;

use App\Models\ManagedObject;
use App\Services\Resource\ScopedCodeUniquenessService;

class EnsureUniqueObjectCode
{
    public function __construct(
        private readonly ScopedCodeUniquenessService $scopedCodeUniquenessService,
    ) {
    }

    public function ensure(int $scopeId, string $code, ?int $ignoreObjectId = null): void
    {
        $this->scopedCodeUniquenessService
            ->ensure(ManagedObject::class, $scopeId, $code, $ignoreObjectId);
    }
}
