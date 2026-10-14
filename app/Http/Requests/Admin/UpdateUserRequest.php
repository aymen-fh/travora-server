<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:users,phone,'.$userId],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'preferred_language' => ['sometimes', 'in:en,ar'],
        ];
    }
}
