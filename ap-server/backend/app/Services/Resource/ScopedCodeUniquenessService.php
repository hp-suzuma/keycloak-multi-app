<?php

namespace App\Services\Resource;

use App\Services\Object\ObjectCodeNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ScopedCodeUniquenessService
{
    public function __construct(
        private readonly ObjectCodeNormalizer $objectCodeNormalizer,
    ) {
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function ensure(string $modelClass, int $scopeId, string $code, ?int $ignoreResourceId = null): void
    {
        $normalizedCode = $this->objectCodeNormalizer->normalize($code);

        $exists = $modelClass::query()
            ->where('scope_id', $scopeId)
            ->where('code', $normalizedCode)
            ->when($ignoreResourceId !== null, fn ($query) => $query->whereKeyNot($ignoreResourceId))
            ->exists();

        if (! $exists) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => [
                'code' => ['The code has already been taken within the target scope.'],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
