<?php

namespace App\Services\Checklist;

use App\Models\Checklist;
use App\Services\Auth\CurrentUser;
use App\Services\Resource\ScopedIndexQueryService;

class ChecklistIndexService
{
    public function __construct(
        private readonly ScopedIndexQueryService $scopedIndexQueryService,
    ) {
    }

    /**
     * @param  array{scope_id?: int, code?: string, name?: string, sort?: string, page?: int, per_page?: int}  $filters
     * @return array{
     *     data: array<int, array{id: int, scope_id: int, code: string, name: string}>,
     *     meta: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         filters: array{scope_id: int|null, code: string|null, name: string|null, sort: string|null}
     *     }
     * }
     */
    public function buildResponse(?CurrentUser $currentUser, array $filters = []): array
    {
        $result = $this->scopedIndexQueryService
            ->query(Checklist::class, $currentUser, $filters);

        return [
            'data' => $result['items']
                ->map(fn (Checklist $checklist): array => ChecklistPayload::fromModel($checklist))
                ->all(),
            'meta' => $result['meta'],
        ];
    }
}
