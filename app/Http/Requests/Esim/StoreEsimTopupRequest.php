<?php

declare(strict_types=1);

namespace App\Http\Requests\Esim;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEsimTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', 'exists:esim_plans,id'],
        ];
    }
}
