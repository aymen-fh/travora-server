<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\Template;

use App\Domain\EsimFulfillmentResult;
use App\Domain\EsimUsageSnapshot;
use App\Integrations\Esim\Contracts\EsimProvider;
use App\Integrations\Esim\DTOs\ProviderHealthDTO;
use App\Integrations\Esim\DTOs\ProviderInboundEvent;
use App\Integrations\Esim\DTOs\ProviderPlanDTO;
use App\Integrations\Esim\DTOs\ProviderPurchaseResult;
use App\Integrations\Esim\DTOs\ProviderRenewResult;
use App\Integrations\Esim\DTOs\ProviderTopUpResult;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\DTOs\RenewRequest;
use App\Integrations\Esim\DTOs\TopUpRequest;
use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Integrations\Esim\Support\ProviderCapabilities;
use App\Models\Provider;

/**
 * Skeleton driver. Copy this class when official provider docs exist.
 * Every operation refuses to guess a vendor API.
 */
final class TemplateEsimProvider implements EsimProvider
{
    private ?Provider $provider = null;

    public function code(): string
    {
        return $this->provider?->code ?? 'template';
    }

    public function using(Provider $provider): self
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            health: false,
            plans: false,
            coverage: false,
            purchase: false,
            esimLookup: false,
        );
    }

    public function health(): ProviderHealthDTO
    {
        throw ProviderNotImplementedException::untilOfficialDocs('health', $this->code());
    }

    public function getPlans(): array
    {
        throw ProviderNotImplementedException::untilOfficialDocs('getPlans', $this->code());
    }

    public function getPlan(string $externalPlanId): ProviderPlanDTO
    {
        throw ProviderNotImplementedException::untilOfficialDocs('getPlan', $this->code());
    }

    public function getCoverage(): array
    {
        throw ProviderNotImplementedException::untilOfficialDocs('getCoverage', $this->code());
    }

    public function purchase(PurchaseRequest $request): ProviderPurchaseResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('purchase', $this->code());
    }

    public function findPurchase(string $idempotencyKey): ?ProviderPurchaseResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('findPurchase', $this->code());
    }

    public function getEsim(string $externalProfileId): EsimFulfillmentResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('getEsim', $this->code());
    }

    public function getUsage(string $externalProfileId): EsimUsageSnapshot
    {
        throw ProviderNotImplementedException::untilOfficialDocs('getUsage', $this->code());
    }

    public function topUp(TopUpRequest $request): ProviderTopUpResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('topUp', $this->code());
    }

    public function renew(RenewRequest $request): ProviderRenewResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('renew', $this->code());
    }

    public function interpretInboundEvent(array $payload): ?ProviderInboundEvent
    {
        throw ProviderNotImplementedException::untilOfficialDocs('interpretInboundEvent', $this->code());
    }
}
