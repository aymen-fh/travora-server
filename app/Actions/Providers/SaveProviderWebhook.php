<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Audit\RecordAuditLog;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;
use App\Models\User;

final class SaveProviderWebhook
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(
        User $actor,
        Provider $provider,
        string $webhook,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->providers->partner($provider)->saveWebhook($webhook);

        ($this->audit)(
            'provider.webhook.saved',
            $actor,
            Provider::class,
            $provider->id,
            null,
            ['webhook' => $webhook],
            $ipAddress,
            $userAgent,
        );
    }
}
