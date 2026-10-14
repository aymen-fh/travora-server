<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SyncProviderServicesRequest extends FormRequest
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
            'services' => ['required', 'array'],
            'services.*.service_type' => ['required', 'string', 'max:40', 'distinct'],
            'services.*.is_enabled' => ['sometimes', 'boolean'],
            'services.*.priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
