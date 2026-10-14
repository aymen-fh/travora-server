<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\ProviderCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderCredential
 */
final class MaskedProviderCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'configured' => true,
            'masked' => true,
            'credential_key' => $this->credential_key,
            'environment' => $this->environment,
            'public_reference' => 'provider:'.$this->provider_id.':credential:'.$this->id,
        ];
    }
}
