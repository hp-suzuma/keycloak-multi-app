<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserAssignmentRequest;
use App\Services\Auth\CurrentUserResolver;
use App\Services\User\UserAssignmentDeleteService;
use Illuminate\Http\Response;

class UserAssignmentDeleteController extends Controller
{
    public function __invoke(
        string $keycloakSub,
        UserAssignmentRequest $request,
        UserAssignmentDeleteService $userAssignmentDeleteService,
        CurrentUserResolver $currentUserResolver,
    ): Response {
        $userAssignmentDeleteService->delete(
            $currentUserResolver->resolve(),
            $keycloakSub,
            $request->payload(),
        );

        return response()->noContent();
    }
}
