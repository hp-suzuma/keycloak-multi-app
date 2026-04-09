<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectManagementApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = (string) env('MANAGEMENT_API_USER', '');
        $expectedPassword = (string) env('MANAGEMENT_API_PASSWORD', '');

        if ($expectedUser === '' || $expectedPassword === '') {
            abort(500, 'Management API credentials are not configured.');
        }

        $providedUser = (string) $request->getUser();
        $providedPassword = (string) $request->getPassword();

        $validUser = hash_equals($expectedUser, $providedUser);
        $validPassword = hash_equals($expectedPassword, $providedPassword);

        if (! $validUser || ! $validPassword) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="RouteAssignments"',
            ]);
        }

        return $next($request);
    }
}
