<?php

namespace App\Services\Resource;

use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use App\Services\Query\ListQueryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ScopedIndexQueryService
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly ListQueryService $listQueryService,
    ) {
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array{scope_id?: int, code?: string, name?: string, sort?: string, page?: int, per_page?: int}  $filters
     * @param  array<int, string>  $allowedSorts
     * @return array{
     *     items: Collection<int, Model>,
     *     meta: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         filters: array{scope_id: int|null, code: string|null, name: string|null, sort: string|null}
     *     }
     * }
     */
    public function query(
        string $modelClass,
        ?CurrentUser $currentUser,
        array $filters = [],
        array $allowedSorts = ['id', 'code', 'name'],
    ): array {
        $accessibleScopeIds = $this->authorizationService
            ->accessibleScopeIds($currentUser, ['object.read']);

        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 20;
        $filteredScopeIds = $accessibleScopeIds;

        if (isset($filters['scope_id'])) {
            $filteredScopeIds = in_array($filters['scope_id'], $accessibleScopeIds, true)
                ? [$filters['scope_id']]
                : [];
        }

        $query = $modelClass::query()
            ->whereIn('scope_id', $filteredScopeIds);

        $this->listQueryService->applyContainsFilters(
            $query,
            array_filter([
                'code' => $filters['code'] ?? null,
                'name' => $filters['name'] ?? null,
            ], fn (?string $value): bool => $value !== null),
        );

        $this->listQueryService->applySort($query, $filters['sort'] ?? null, $allowedSorts);

        $paginationMeta = $this->listQueryService->paginationMeta($query, $page, $perPage);
        $items = $this->listQueryService->page(clone $query, $page, $perPage);

        return [
            'items' => $items,
            'meta' => [
                ...$paginationMeta,
                'filters' => [
                    'scope_id' => $filters['scope_id'] ?? null,
                    'code' => $filters['code'] ?? null,
                    'name' => $filters['name'] ?? null,
                    'sort' => $filters['sort'] ?? null,
                ],
            ],
        ];
    }
}
