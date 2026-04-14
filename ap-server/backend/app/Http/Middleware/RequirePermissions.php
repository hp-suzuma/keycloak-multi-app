<?php

namespace App\Http\Middleware;

use App\Services\Auth\CurrentUserResolver;
use App\Services\Authorization\AuthorizationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermissions
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$requiredPermissions,
    ): Response|JsonResponse {
        $currentUser = app(CurrentUserResolver::class)->resolve();
        $authorizationService = app(AuthorizationService::class);

        if (! $authorizationService->hasRequiredPermissions($currentUser, $requiredPermissions)) {
            return response()->json([
                'message' => 'Forbidden',
                'required_permissions' => $requiredPermissions,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
