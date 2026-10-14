<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;

final class QueryProviderBalance
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {}

    /**
     * @return array{balance: int, currency: string, scale: int}
     */
    public function __invoke(Provider $provider): array
    {
        return [
            'balance' => $this->providers->partner($provider)->merchantBalance(),
            'currency' => 'USD',
            'scale' => 10000,
        ];
    }
}
