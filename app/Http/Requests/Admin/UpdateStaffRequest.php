<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\Rbac\RolePermissionMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStaffRequest extends FormRequest
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
        $staffId = $this->route('staff')?->id ?? $this->route('staff');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$staffId],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'unique:users,phone,'.$staffId],
            'role' => ['sometimes', 'string', Rule::in(RolePermissionMatrix::assignableStaffRoles())],
        ];
    }
}
