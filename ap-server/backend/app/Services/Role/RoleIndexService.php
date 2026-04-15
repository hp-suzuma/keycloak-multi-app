<?php

namespace App\Services\Role;

use App\Models\Role;
use App\Services\Auth\CurrentUser;
use App\Services\Query\ListQueryService;

class RoleIndexService
{
    public function __construct(
        private readonly ListQueryService $listQueryService,
    ) {
    }

    /**
     * @param  array{
     *     scope_layer?: string,
     *     permission_role?: string,
     *     slug?: string,
     *     name?: string,
     *     sort?: string
     * }  $filters
     * @return array{
     *     data: array<int, array{
     *         id: int,
     *         slug: string,
     *         name: string,
     *         scope_layer: string,
     *         permission_role: string,
     *         permissions: array<int, array{id: int, slug: string, name: string}>
     *     }>
     * }
     */
    public function buildResponse(?CurrentUser $currentUser, array $filters = []): array
    {
        unset($currentUser);

        $query = Role::query()
            ->with('permissions');

        if (isset($filters['scope_layer'])) {
            $query->where('scope_layer', $filters['scope_layer']);
        }

        if (isset($filters['permission_role'])) {
            $query->where('permission_role', $filters['permission_role']);
        }

        $this->listQueryService->applyContainsFilters(
            $query,
            array_filter([
                'slug' => $filters['slug'] ?? null,
                'name' => $filters['name'] ?? null,
            ], fn (?string $value): bool => $value !== null),
        );

        $this->listQueryService->applySort(
            $query,
            $filters['sort'] ?? null,
            ['slug', 'name', 'scope_layer', 'permission_role'],
            'slug',
        );

        return [
            'data' => $query
                ->get()
                ->map(fn (Role $role): array => RolePayload::fromModel($role))
                ->all(),
        ];
    }
}
