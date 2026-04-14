<?php

namespace App\Services\Policy;

use App\Models\Policy;
use App\Services\Resource\ScopedCodeUniquenessService;

class EnsureUniquePolicyCode
{
    public function __construct(
        private readonly ScopedCodeUniquenessService $scopedCodeUniquenessService,
    ) {
    }

    public function ensure(int $scopeId, string $code, ?int $ignorePolicyId = null): void
    {
        $this->scopedCodeUniquenessService
            ->ensure(Policy::class, $scopeId, $code, $ignorePolicyId);
    }
}
