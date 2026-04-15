<?php

namespace App\Services\User;

use App\Models\ApUser;
use App\Services\Auth\CurrentUser;
use App\Services\Authorization\AuthorizationService;
use App\Services\Query\ListQueryService;

class UserIndexService
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly ListQueryService $listQueryService,
    ) {
    }

    /**
     * @param  array{
     *     scope_id?: int,
     *     keycloak_sub?: string,
     *     keyword?: string,
     *     sort?: string,
     *     page?: int,
     *     per_page?: int
     * }  $filters
     * @return array{
     *     data: array<int, array{
     *         keycloak_sub: string,
     *         display_name: string|null,
     *         email: string|null,
     *         assignments: array<int, array{
     *             scope: array{id: int, layer: string, code: string, name: string, parent_scope_id: int|null},
     *             role: array{id: int, slug: string, name: string, scope_layer: string, permission_role: string},
     *             permissions: array<int, array{id: int, slug: string, name: string}>
     *         }>,
     *         permissions: array<int, string>
     *     }>,
     *     meta: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         filters: array{
     *             scope_id: int|null,
     *             keycloak_sub: string|null,
     *             keyword: string|null,
     *             sort: string|null
     *         }
     *     }
     * }
     */
    public function buildResponse(?CurrentUser $currentUser, array $filters = []): array
    {
        $manageableScopeIds = $this->authorizationService
            ->accessibleScopeIds($currentUser, ['user.manage']);

        $visibleScopeIds = $manageableScopeIds;

        if (isset($filters['scope_id'])) {
            $visibleScopeIds = in_array($filters['scope_id'], $manageableScopeIds, true)
                ? [$filters['scope_id']]
                : [];
        }

        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 20;

        $query = ApUser::query()
            ->whereHas('roleAssignments', fn ($assignments) => $assignments->whereIn('scope_id', $visibleScopeIds));

        $this->listQueryService->applyContainsFilters(
            $query,
            array_filter([
                'keycloak_sub' => $filters['keycloak_sub'] ?? null,
            ], fn (?string $value): bool => $value !== null),
        );

        if (isset($filters['keyword'])) {
            $query->where(function ($keywordQuery) use ($filters): void {
                $keywordQuery
                    ->where('display_name', 'like', '%'.$filters['keyword'].'%')
                    ->orWhere('email', 'like', '%'.$filters['keyword'].'%');
            });
        }

        $this->listQueryService->applySort(
            $query,
            $filters['sort'] ?? null,
            ['keycloak_sub', 'display_name', 'email'],
            'email',
        );

        $paginationMeta = $this->listQueryService->paginationMeta($query, $page, $perPage);

        $users = $this->listQueryService
            ->page(clone $query->with([
                'roleAssignments' => fn ($assignments) => $assignments
                    ->whereIn('scope_id', $visibleScopeIds)
                    ->with([
                        'scope',
                        'role.permissions',
                    ]),
            ]), $page, $perPage);

        return [
            'data' => $users
                ->map(fn (ApUser $user): array => UserPayload::fromModel($user))
                ->all(),
            'meta' => [
                ...$paginationMeta,
                'filters' => [
                    'scope_id' => $filters['scope_id'] ?? null,
                    'keycloak_sub' => $filters['keycloak_sub'] ?? null,
                    'keyword' => $filters['keyword'] ?? null,
                    'sort' => $filters['sort'] ?? null,
                ],
            ],
        ];
    }
}
