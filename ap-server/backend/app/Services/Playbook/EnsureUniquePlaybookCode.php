<?php

namespace App\Services\Playbook;

use App\Models\Playbook;
use App\Services\Resource\ScopedCodeUniquenessService;

class EnsureUniquePlaybookCode
{
    public function __construct(
        private readonly ScopedCodeUniquenessService $scopedCodeUniquenessService,
    ) {
    }

    public function ensure(int $scopeId, string $code, ?int $ignorePlaybookId = null): void
    {
        $this->scopedCodeUniquenessService
            ->ensure(Playbook::class, $scopeId, $code, $ignorePlaybookId);
    }
}
