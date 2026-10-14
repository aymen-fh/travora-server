<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

use App\Models\Provider;
use App\Models\ProviderCredential;

final class ProviderSecretStore
{
    /**
     * Returns the stored secret reference (vault pointer), never a live provider key invented here.
     */
    public function reference(Provider $provider, string $credentialKey, string $environment = 'test'): ?string
    {
        $credential = ProviderCredential::query()
            ->where('provider_id', $provider->id)
            ->where('environment', $environment)
            ->where('credential_key', $credentialKey)
            ->first();

        if ($credential !== null) {
            return $credential->secret_reference;
        }

        // Fallback: check other environments in database
        $fallbackCredential = ProviderCredential::query()
            ->where('provider_id', $provider->id)
            ->where('credential_key', $credentialKey)
            ->first();

        if ($fallbackCredential !== null) {
            return $fallbackCredential->secret_reference;
        }

        // Fallback: config / env for esimaccess (outside of tests)
        if (! app()->environment('testing') && $provider->code === 'esimaccess') {
            if ($credentialKey === 'access_code') {
                $val = config('esim.esimaccess.access_code');
                return filled($val) ? (string) $val : null;
            }
            if ($credentialKey === 'secret_key') {
                $val = config('esim.esimaccess.secret_key');
                return filled($val) ? (string) $val : null;
            }
        }

        return null;
    }
}
