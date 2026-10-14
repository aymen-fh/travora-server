<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncProviderCoverageRequest extends FormRequest
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
            'coverage' => ['required', 'array'],
            'coverage.*.country_id' => ['required', 'uuid', 'exists:countries,id'],
            'coverage.*.service_type' => ['required', 'string', 'max:40'],
            'coverage.*.status' => ['sometimes', Rule::enum(RecordStatus::class)],
        ];
    }
}
