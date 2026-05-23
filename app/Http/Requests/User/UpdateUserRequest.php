<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', Rule::enum(Role::class)],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (! $this->user()->isAdmin()) {
            unset($data['role']);
        }

        return $data;
    }
}
