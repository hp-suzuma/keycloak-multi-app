<?php

namespace App\Services\Object;

class ObjectCodeNormalizer
{
    public function normalize(string $code): string
    {
        $normalized = trim($code);
        $normalized = preg_replace('/[\s_]+/', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;

        return mb_strtolower($normalized);
    }
}
