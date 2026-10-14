<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SyncPlanNetworksRequest extends FormRequest
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
            'network_ids' => ['required', 'array'],
            'network_ids.*' => ['uuid', 'exists:network_operators,id'],
        ];
    }
}
