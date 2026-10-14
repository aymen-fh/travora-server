<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEsimPlanRequest extends FormRequest
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
            'provider_id' => ['required', 'uuid', 'exists:providers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'data_mb' => ['nullable', 'integer', 'min:0'],
            'is_unlimited' => ['sometimes', 'boolean'],
            'validity_days' => ['required', 'integer', 'min:1'],
            'hotspot_supported' => ['sometimes', 'boolean'],
            'auto_topup_supported' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(RecordStatus::class)],
        ];
    }
}
