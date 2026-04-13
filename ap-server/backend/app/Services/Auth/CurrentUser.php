<?php

namespace App\Services\Auth;

use App\Models\User;

readonly class CurrentUser
{
    public function __construct(
        public int|string $id,
        public string $name,
        public string $email,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
        );
    }

    /**
     * @return array{id: int|string, name: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
