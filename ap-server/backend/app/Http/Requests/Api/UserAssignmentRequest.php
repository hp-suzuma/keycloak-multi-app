<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UserAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{scope_id: int, role_id: int}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'scope_id' => (int) $validated['scope_id'],
            'role_id' => (int) $validated['role_id'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'scope_id' => ['required', 'integer', 'exists:scopes,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
