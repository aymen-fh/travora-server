<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

use App\Enums\ProviderCapability;

final readonly class ProviderCapabilities
{
    public function __construct(
        public bool $health = true,
        public bool $plans = true,
        public bool $coverage = true,
        public bool $purchase = true,
        public bool $esimLookup = true,
        public bool $usage = false,
        public bool $topup = false,
        public bool $renewal = false,
        public bool $webhooks = false,
    ) {}

    public function supports(ProviderCapability $capability): bool
    {
        return match ($capability) {
            ProviderCapability::Health => $this->health,
            ProviderCapability::Plans => $this->plans,
            ProviderCapability::Coverage => $this->coverage,
            ProviderCapability::Purchase => $this->purchase,
            ProviderCapability::EsimLookup => $this->esimLookup,
            ProviderCapability::Usage => $this->usage,
            ProviderCapability::Topup => $this->topup,
            ProviderCapability::Renewal => $this->renewal,
            ProviderCapability::Webhooks => $this->webhooks,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            health: (bool) ($data['health'] ?? true),
            plans: (bool) ($data['plans'] ?? true),
            coverage: (bool) ($data['coverage'] ?? true),
            purchase: (bool) ($data['purchase'] ?? true),
            esimLookup: (bool) ($data['esim_lookup'] ?? true),
            usage: (bool) ($data['usage'] ?? false),
            topup: (bool) ($data['topup'] ?? false),
            renewal: (bool) ($data['renewal'] ?? false),
            webhooks: (bool) ($data['webhooks'] ?? false),
        );
    }
}
