<?php

namespace App\Services\Health;

class HealthService
{
    /**
     * @return array{status: string}
     */
    public function buildResponse(): array
    {
        return [
            'status' => 'ok',
        ];
    }
}
