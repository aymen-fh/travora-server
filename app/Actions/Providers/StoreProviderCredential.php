<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Provider;
use App\Models\ProviderCredential;
use App\Models\User;
use Illuminate\Http\Request;

final class StoreProviderCredential
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(
        User $actor,
        Provider $provider,
        string $environment,
        string $credentialKey,
        string $secretReference,
        ?Request $request = null,
    ): ProviderCredential {
        $credential = ProviderCredential::query()->firstOrNew([
            'provider_id' => $provider->id,
            'environment' => $environment,
            'credential_key' => $credentialKey,
        ]);

        $credential->secret_reference = $secretReference;
        $credential->save();

        ($this->audit)(
            'provider.credentials.updated',
            $actor,
            ProviderCredential::class,
            $credential->id,
            null,
            [
                'provider_id' => $provider->id,
                'environment' => $environment,
                'credential_key' => $credentialKey,
                'configured' => true,
                'masked' => true,
            ],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $credential;
    }
}
