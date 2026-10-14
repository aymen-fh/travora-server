<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;

final class QueryProviderDiscountRule
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(Provider $provider, string $discountRuleCode): array
    {
        return $this->providers->partner($provider)->discountRules($discountRuleCode);
    }
}
