<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;

class SsoUser implements Authenticatable
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly ?string $email,
        private readonly array $claims,
    ) {
    }

    public static function fromClaims(array $claims): self
    {
        return new self(
            id: (string) ($claims['sub'] ?? ''),
            name: (string) ($claims['preferred_username'] ?? $claims['name'] ?? 'user'),
            email: $claims['email'] ?? null,
            claims: $claims,
        );
    }

    public function getAuthIdentifierName(): string
    {
        return 'sub';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function toArray(): array
    {
        return [
            'sub' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ] + $this->claims;
    }
}
