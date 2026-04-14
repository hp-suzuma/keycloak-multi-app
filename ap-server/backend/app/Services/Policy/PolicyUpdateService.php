<?php

namespace App\Services\Policy;

use App\Services\Auth\CurrentUser;
use App\Services\Object\EnsureAuthorizedScope;
use App\Services\Object\ObjectCodeNormalizer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class PolicyUpdateService
{
    public function __construct(
        private readonly FindAuthorizedPolicy $findAuthorizedPolicy,
        private readonly EnsureAuthorizedScope $ensureAuthorizedScope,
        private readonly EnsureUniquePolicyCode $ensureUniquePolicyCode,
        private readonly ObjectCodeNormalizer $objectCodeNormalizer,
    ) {
    }

    /**
     * @param  array{scope_id?: int, code?: string, name?: string}  $attributes
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $policyId, array $attributes): array
    {
        if ($attributes === []) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'payload' => ['At least one of scope_id, code, or name is required.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $policy = $this->findAuthorizedPolicy
            ->resolve($currentUser, $policyId, 'object.update');

        if (isset($attributes['scope_id']) && $attributes['scope_id'] !== $policy->scope_id) {
            $this->ensureAuthorizedScope->ensure($currentUser, $attributes['scope_id'], 'object.create');
        }

        $nextScopeId = $attributes['scope_id'] ?? $policy->scope_id;
        $nextCode = isset($attributes['code'])
            ? $this->objectCodeNormalizer->normalize($attributes['code'])
            : $policy->code;

        if ($nextScopeId !== $policy->scope_id || $nextCode !== $policy->code) {
            $this->ensureUniquePolicyCode->ensure($nextScopeId, $nextCode, $policy->id);
        }

        $policy->fill([
            'scope_id' => $nextScopeId,
            'code' => $nextCode,
            'name' => $attributes['name'] ?? $policy->name,
        ]);
        $policy->save();

        return [
            'data' => PolicyPayload::fromModel($policy->fresh()),
        ];
    }
}
