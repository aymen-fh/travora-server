<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\Rbac\RolePermissionMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'unique:users,phone'],
            'role' => ['required', 'string', Rule::in(RolePermissionMatrix::assignableStaffRoles())],
            'status' => ['sometimes', 'string', Rule::in(['active', 'suspended'])],
        ];
    }
}
