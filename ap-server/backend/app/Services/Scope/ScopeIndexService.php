<?php

namespace App\Services\Scope;

use App\Models\Scope;
use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use App\Services\Query\ListQueryService;

class ScopeIndexService
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly ListQueryService $listQueryService,
    ) {
    }

    /**
     * @param  array{
     *     layer?: string,
     *     parent_scope_id?: int,
     *     code?: string,
     *     name?: string,
     *     sort?: string
     * }  $filters
     * @return array{
     *     data: array<int, array{
     *         id: int,
     *         layer: string,
     *         code: string,
     *         name: string,
     *         parent_scope_id: int|null
     *     }>
     * }
     */
    public function buildResponse(?CurrentUser $currentUser, array $filters = []): array
    {
        $query = Scope::query()
            ->whereIn(
                'id',
                $this->authorizationService->accessibleScopeIds($currentUser, ['user.manage']),
            );

        if (isset($filters['layer'])) {
            $query->where('layer', $filters['layer']);
        }

        if (isset($filters['parent_scope_id'])) {
            $query->where('parent_scope_id', $filters['parent_scope_id']);
        }

        $this->listQueryService->applyContainsFilters(
            $query,
            array_filter([
                'code' => $filters['code'] ?? null,
                'name' => $filters['name'] ?? null,
            ], fn (?string $value): bool => $value !== null),
        );

        $this->listQueryService->applySort(
            $query,
            $filters['sort'] ?? null,
            ['id', 'layer', 'code', 'name'],
            'id',
        );

        return [
            'data' => $query
                ->get()
                ->map(fn (Scope $scope): array => ScopePayload::fromModel($scope))
                ->all(),
        ];
    }
}
