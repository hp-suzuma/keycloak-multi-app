<?php

namespace App\Services\Checklist;

use App\Models\Checklist;
use App\Services\Resource\ScopedCodeUniquenessService;

class EnsureUniqueChecklistCode
{
    public function __construct(
        private readonly ScopedCodeUniquenessService $scopedCodeUniquenessService,
    ) {
    }

    public function ensure(int $scopeId, string $code, ?int $ignoreChecklistId = null): void
    {
        $this->scopedCodeUniquenessService
            ->ensure(Checklist::class, $scopeId, $code, $ignoreChecklistId);
    }
}
