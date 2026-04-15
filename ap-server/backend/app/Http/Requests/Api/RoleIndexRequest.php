<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleIndexRequest extends FormRequest
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
        return $this->validated();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'scope_layer' => ['nullable', Rule::in(['server', 'service', 'tenant'])],
            'permission_role' => ['nullable', Rule::in(['admin', 'operator', 'viewer', 'user_manager'])],
            'slug' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['slug', '-slug', 'name', '-name', 'scope_layer', '-scope_layer', 'permission_role', '-permission_role'])],
        ];
    }
}
