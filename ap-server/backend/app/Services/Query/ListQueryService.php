<?php

namespace App\Services\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListQueryService
{
    /**
     * @param  array<string, string>  $containsFilters
     */
    public function applyContainsFilters(Builder $query, array $containsFilters): Builder
    {
        foreach ($containsFilters as $column => $value) {
            $query->where($column, 'like', '%'.$value.'%');
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $allowedSorts
     */
    public function applySort(Builder $query, ?string $sort, array $allowedSorts, string $fallback = 'id'): Builder
    {
        $sortValue = $sort ?? $fallback;
        $descending = str_starts_with($sortValue, '-');
        $column = ltrim($sortValue, '-');

        if (! in_array($column, $allowedSorts, true)) {
            $column = $fallback;
            $descending = false;
        }

        return $query
            ->orderBy($column, $descending ? 'desc' : 'asc')
            ->orderBy($query->getModel()->getQualifiedKeyName());
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    public function paginationMeta(Builder $query, int $page, int $perPage): array
    {
        $total = (clone $query)->count();

        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max((int) ceil($total / $perPage), 1),
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    public function page(Builder $query, int $page, int $perPage): Collection
    {
        return $query
            ->forPage($page, $perPage)
            ->get();
    }
}
