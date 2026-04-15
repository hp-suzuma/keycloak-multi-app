<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScopeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        if (isset($validated['parent_scope_id'])) {
            $validated['parent_scope_id'] = (int) $validated['parent_scope_id'];
        }

        return $validated;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'layer' => ['nullable', Rule::in(['server', 'service', 'tenant'])],
            'parent_scope_id' => ['nullable', 'integer', 'exists:scopes,id'],
            'code' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['id', '-id', 'layer', '-layer', 'code', '-code', 'name', '-name'])],
        ];
    }
}
