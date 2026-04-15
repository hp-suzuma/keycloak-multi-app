<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserResolver;
use App\Services\User\UserAssignmentDeleteService;
use Illuminate\Http\Response;

class UserAssignmentItemDeleteController extends Controller
{
    public function __invoke(
        string $keycloakSub,
        int $assignmentId,
        UserAssignmentDeleteService $userAssignmentDeleteService,
        CurrentUserResolver $currentUserResolver,
    ): Response {
        $userAssignmentDeleteService->deleteById(
            $currentUserResolver->resolve(),
            $keycloakSub,
            $assignmentId,
        );

        return response()->noContent();
    }
}
