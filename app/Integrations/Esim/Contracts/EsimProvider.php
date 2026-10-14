<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Contracts;

use App\Domain\EsimFulfillmentResult;
use App\Domain\EsimUsageSnapshot;
use App\Integrations\Esim\DTOs\ProviderCoverageDTO;
use App\Integrations\Esim\DTOs\ProviderHealthDTO;
use App\Integrations\Esim\DTOs\ProviderInboundEvent;
use App\Integrations\Esim\DTOs\ProviderPlanDTO;
use App\Integrations\Esim\DTOs\ProviderPurchaseResult;
use App\Integrations\Esim\DTOs\ProviderRenewResult;
use App\Integrations\Esim\DTOs\ProviderTopUpResult;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\DTOs\RenewRequest;
use App\Integrations\Esim\DTOs\TopUpRequest;
use App\Integrations\Esim\Support\ProviderCapabilities;
use App\Models\Provider;

/**
 * Travora-facing eSIM port. Drivers map official provider docs into these methods.
 * Do not add vendor endpoints, auth headers, or webhook signatures here.
 */
interface EsimProvider
{
    public function code(): string;

    public function using(Provider $provider): self;

    public function capabilities(): ProviderCapabilities;

    public function health(): ProviderHealthDTO;

    /**
     * @return list<ProviderPlanDTO>
     */
    public function getPlans(): array;

    public function getPlan(string $externalPlanId): ProviderPlanDTO;

    /**
     * @return list<ProviderCoverageDTO>
     */
    public function getCoverage(): array;

    public function purchase(PurchaseRequest $request): ProviderPurchaseResult;

    public function findPurchase(string $idempotencyKey): ?ProviderPurchaseResult;

    public function getEsim(string $externalProfileId): EsimFulfillmentResult;

    public function getUsage(string $externalProfileId): EsimUsageSnapshot;

    public function topUp(TopUpRequest $request): ProviderTopUpResult;

    public function renew(RenewRequest $request): ProviderRenewResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function interpretInboundEvent(array $payload): ?ProviderInboundEvent;
}
