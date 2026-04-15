<?php

namespace App\Services\User;

use App\Services\Auth\CurrentUser;

class UserAssignmentDeleteService
{
    public function __construct(
        private readonly FindExistingUser $findExistingUser,
        private readonly FindVisibleAssignment $findVisibleAssignment,
    ) {
    }

    /**
     * @param  array{scope_id: int, role_id: int}  $attributes
     */
    public function delete(?CurrentUser $currentUser, string $keycloakSub, array $attributes): void
    {
        $this->findExistingUser->resolve($keycloakSub);

        $assignment = $this->findVisibleAssignment->resolve(
            $currentUser,
            $keycloakSub,
            $attributes['scope_id'],
            $attributes['role_id'],
        );

        $assignment->delete();
    }

    public function deleteById(?CurrentUser $currentUser, string $keycloakSub, int $assignmentId): void
    {
        $this->findExistingUser->resolve($keycloakSub);

        $assignment = $this->findVisibleAssignment->resolveById(
            $currentUser,
            $keycloakSub,
            $assignmentId,
        );

        $assignment->delete();
    }
}
