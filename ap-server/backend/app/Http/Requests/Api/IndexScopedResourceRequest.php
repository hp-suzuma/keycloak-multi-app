<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexScopedResourceRequest extends FormRequest
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

        if (isset($validated['scope_id'])) {
            $validated['scope_id'] = (int) $validated['scope_id'];
        }

        if (isset($validated['page'])) {
            $validated['page'] = (int) $validated['page'];
        }

        if (isset($validated['per_page'])) {
            $validated['per_page'] = (int) $validated['per_page'];
        }

        return $validated;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'scope_id' => ['nullable', 'integer', 'exists:scopes,id'],
            'code' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['id', '-id', 'code', '-code', 'name', '-name'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
