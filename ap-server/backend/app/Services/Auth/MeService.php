<?php

namespace App\Services\Auth;

class MeService
{
    /**
     * @return array{current_user: array{id: int|string, name: string, email: string}|null}
     */
    public function buildResponse(?CurrentUser $currentUser): array
    {
        return [
            'current_user' => $currentUser?->toArray(),
        ];
    }
}
