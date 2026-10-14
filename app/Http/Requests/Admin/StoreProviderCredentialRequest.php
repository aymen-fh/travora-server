<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProviderCredentialRequest extends FormRequest
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
            'environment' => ['required', 'string', 'max:20'],
            'credential_key' => ['required', 'string', 'max:100'],
            'secret_reference' => ['required', 'string', 'max:2000'],
        ];
    }
}
