<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProviderType;
use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProviderRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:80', 'unique:providers,code'],
            'type' => ['nullable', Rule::enum(ProviderType::class)],
            'status' => ['nullable', Rule::enum(RecordStatus::class)],
            'priority' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
