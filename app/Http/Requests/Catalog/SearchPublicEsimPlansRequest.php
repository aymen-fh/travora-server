<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class SearchPublicEsimPlansRequest extends FormRequest
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
            'currency' => ['nullable', 'string', 'size:3'],
            'country_iso2' => ['nullable', 'string', 'size:2'],
            'region_code' => ['nullable', 'string', 'max:16'],
            'min_data_mb' => ['nullable', 'integer', 'min:0'],
            'max_data_mb' => ['nullable', 'integer', 'min:0'],
            'min_validity_days' => ['nullable', 'integer', 'min:0'],
            'max_validity_days' => ['nullable', 'integer', 'min:0'],
            'min_price_minor' => ['nullable', 'integer', 'min:0'],
            'max_price_minor' => ['nullable', 'integer', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }
}
