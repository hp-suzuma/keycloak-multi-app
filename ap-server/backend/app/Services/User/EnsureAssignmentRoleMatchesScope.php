<?php

namespace App\Services\User;

use App\Models\Role;
use App\Models\Scope;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssignmentRoleMatchesScope
{
    public function resolve(Scope $scope, int $roleId): Role
    {
        $role = Role::query()->findOrFail($roleId);

        if ($role->scope_layer !== $scope->layer) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'role_id' => ['The selected role does not match the target scope layer.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $role;
    }
}
