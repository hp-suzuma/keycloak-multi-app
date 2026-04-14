<?php

namespace App\Services\Object;

use App\Models\ManagedObject;
use App\Services\Auth\CurrentUser;
use App\Services\Query\ListQueryService;
use App\Services\Resource\ScopedIndexQueryService;

class ObjectIndexService
{
    public function __construct(
        private readonly ScopedIndexQueryService $scopedIndexQueryService,
        private readonly ObjectCodeNormalizer $objectCodeNormalizer,
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
        if (isset($filters['code'])) {
            $filters['code'] = $this->objectCodeNormalizer->normalize($filters['code']);
        }

        $result = $this->scopedIndexQueryService
            ->query(ManagedObject::class, $currentUser, $filters);

        return [
            'data' => $result['items']
                ->map(fn (ManagedObject $managedObject): array => ObjectPayload::fromModel($managedObject))
                ->all(),
            'meta' => $result['meta'],
        ];
    }
}
