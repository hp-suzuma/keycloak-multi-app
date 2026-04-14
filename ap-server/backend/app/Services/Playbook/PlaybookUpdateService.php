<?php

namespace App\Services\Playbook;

use App\Services\Auth\CurrentUser;
use App\Services\Object\EnsureAuthorizedScope;
use App\Services\Object\ObjectCodeNormalizer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class PlaybookUpdateService
{
    public function __construct(
        private readonly FindAuthorizedPlaybook $findAuthorizedPlaybook,
        private readonly EnsureAuthorizedScope $ensureAuthorizedScope,
        private readonly EnsureUniquePlaybookCode $ensureUniquePlaybookCode,
        private readonly ObjectCodeNormalizer $objectCodeNormalizer,
    ) {
    }

    /**
     * @param  array{scope_id?: int, code?: string, name?: string}  $attributes
     * @return array{data: array{id: int, scope_id: int, code: string, name: string}}
     */
    public function buildResponse(?CurrentUser $currentUser, int $playbookId, array $attributes): array
    {
        if ($attributes === []) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'payload' => ['At least one of scope_id, code, or name is required.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $playbook = $this->findAuthorizedPlaybook
            ->resolve($currentUser, $playbookId, 'object.update');

        if (isset($attributes['scope_id']) && $attributes['scope_id'] !== $playbook->scope_id) {
            $this->ensureAuthorizedScope->ensure($currentUser, $attributes['scope_id'], 'object.create');
        }

        $nextScopeId = $attributes['scope_id'] ?? $playbook->scope_id;
        $nextCode = isset($attributes['code'])
            ? $this->objectCodeNormalizer->normalize($attributes['code'])
            : $playbook->code;

        if ($nextScopeId !== $playbook->scope_id || $nextCode !== $playbook->code) {
            $this->ensureUniquePlaybookCode->ensure($nextScopeId, $nextCode, $playbook->id);
        }

        $playbook->fill([
            'scope_id' => $nextScopeId,
            'code' => $nextCode,
            'name' => $attributes['name'] ?? $playbook->name,
        ]);
        $playbook->save();

        return [
            'data' => PlaybookPayload::fromModel($playbook->fresh()),
        ];
    }
}
