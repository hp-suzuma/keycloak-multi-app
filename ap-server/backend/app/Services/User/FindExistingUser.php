<?php

namespace App\Services\User;

use App\Models\ApUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FindExistingUser
{
    public function resolve(string $keycloakSub): ApUser
    {
        $user = ApUser::query()->find($keycloakSub);

        if ($user === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Not Found',
            ], Response::HTTP_NOT_FOUND));
        }

        return $user;
    }
}
