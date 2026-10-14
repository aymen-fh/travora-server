<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\Fake;

use App\Domain\EsimFulfillmentResult;
use App\Domain\EsimUsageSnapshot;
use App\Enums\HealthStatus;
use App\Integrations\Esim\Contracts\EsimProvider;
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
use App\Integrations\Esim\Exceptions\ProviderMalformedException;
use App\Integrations\Esim\Exceptions\ProviderRejectedException;
use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Integrations\Esim\Exceptions\ProviderUnavailableException;
use App\Integrations\Esim\Exceptions\ProviderUnusableCredentialsException;
use App\Integrations\Esim\Support\ProviderApiLogger;
use App\Integrations\Esim\Support\ProviderCapabilities;
use App\Models\Provider;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * In-process driver for tests and local development. It never performs HTTP
 * and does not describe a real vendor API.
 */
final class FakeProvider implements EsimProvider
{
    private ?Provider $provider = null;

    public function __construct(
        private readonly ProviderApiLogger $logger,
    ) {}

    public function code(): string
    {
        return 'fake';
    }

    public function using(Provider $provider): self
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    public function capabilities(): ProviderCapabilities
    {
        $config = $this->provider?->services()
            ->where('service_type', 'esim')
            ->first()
            ?->config['capabilities'] ?? null;

        if (is_array($config)) {
            return ProviderCapabilities::fromArray($config);
        }

        return new ProviderCapabilities(
            usage: true,
            topup: true,
            renewal: true,
            webhooks: true,
        );
    }

    public function health(): ProviderHealthDTO
    {
        $this->applyFailureScenarios('health');
        $this->record('health', true, 4);

        return new ProviderHealthDTO(HealthStatus::Healthy, 4, 'fake driver reachable');
    }

    public function getPlans(): array
    {
        $this->record('getPlans', true);

        return $this->catalog();
    }

    public function getPlan(string $externalPlanId): ProviderPlanDTO
    {
        foreach ($this->catalog() as $plan) {
            if ($plan->externalPlanId === $externalPlanId) {
                $this->record('getPlan', true);

                return $plan;
            }
        }

        $this->record('getPlan', false, error: 'unknown plan');
        throw ProviderRejectedException::because('Unknown plan.', $this->code());
    }

    public function getCoverage(): array
    {
        $this->record('getCoverage', true);

        return [
            new ProviderCoverageDTO('TR', 'Turkey', 'TUR'),
            new ProviderCoverageDTO('DE', 'Germany', 'DEU'),
            new ProviderCoverageDTO('FR', 'France', 'FRA'),
            new ProviderCoverageDTO('IT', 'Italy', 'ITA'),
            new ProviderCoverageDTO('ES', 'Spain', 'ESP'),
        ];
    }

    public function purchase(PurchaseRequest $request): ProviderPurchaseResult
    {
        $this->applyFailureScenarios('purchase', $request->idempotencyKey, $request->externalPlanId);
        $this->getPlan($request->externalPlanId);

        if ($this->scenario() === 'malformed') {
            $this->record('purchase', false, error: 'malformed');
            throw ProviderMalformedException::result($this->code());
        }

        $result = $this->rememberPurchase($request->idempotencyKey, $request->externalPlanId);
        $this->record('purchase', true);

        return $result;
    }

    public function findPurchase(string $idempotencyKey): ?ProviderPurchaseResult
    {
        $hit = Cache::get($this->cacheKey($idempotencyKey));
        $this->record('findPurchase', is_array($hit));

        if (! is_array($hit)) {
            return null;
        }

        return $this->purchaseResult(
            (string) $hit['order_id'],
            (string) $hit['profile_id'],
            (string) $hit['plan_id'],
        );
    }

    public function getEsim(string $externalProfileId): EsimFulfillmentResult
    {
        $this->record('getEsim', true);

        return $this->fulfillment($externalProfileId, 'fake-tr-10gb');
    }

    public function getUsage(string $externalProfileId): EsimUsageSnapshot
    {
        $this->record('getUsage', true);

        return new EsimUsageSnapshot(usedMb: 128, remainingMb: 896, source: 'provider');
    }

    public function topUp(TopUpRequest $request): ProviderTopUpResult
    {
        $this->applyFailureScenarios('topUp');
        $plan = $this->getPlan($request->externalPlanId);
        $this->record('topUp', true);

        return new ProviderTopUpResult(
            externalReference: 'fake-topup-'.$this->token($request->idempotencyKey),
            status: 'completed',
            dataMbAdded: $plan->dataMb,
        );
    }

    public function renew(RenewRequest $request): ProviderRenewResult
    {
        $this->applyFailureScenarios('renew');
        $plan = $this->getPlan($request->externalPlanId);
        $this->record('renew', true);

        return new ProviderRenewResult(
            externalReference: 'fake-renew-'.$this->token($request->idempotencyKey),
            status: 'completed',
            validityDaysAdded: $plan->validityDays,
        );
    }

    public function interpretInboundEvent(array $payload): ?ProviderInboundEvent
    {
        if (($payload['travora_fixture'] ?? null) !== 'usage') {
            return null;
        }

        $profileId = isset($payload['external_profile_id']) ? (string) $payload['external_profile_id'] : null;
        $used = isset($payload['used_mb']) ? (int) $payload['used_mb'] : 0;
        $remaining = isset($payload['remaining_mb']) ? (int) $payload['remaining_mb'] : null;

        return new ProviderInboundEvent(
            type: 'usage',
            externalProfileId: $profileId,
            usage: new EsimUsageSnapshot($used, $remaining, 'provider'),
        );
    }

    /**
     * @return list<ProviderPlanDTO>
     */
    private function catalog(): array
    {
        return [
            new ProviderPlanDTO(
                externalPlanId: 'fake-tr-10gb',
                name: 'Turkey 10GB 30 days',
                dataMb: 10240,
                isUnlimited: false,
                validityDays: 30,
                costMinor: 1200,
                currency: 'USD',
                countryIso2: ['TR'],
                description: 'Deterministic fake catalog plan.',
            ),
            new ProviderPlanDTO(
                externalPlanId: 'fake-eu-5gb',
                name: 'Europe 5GB 15 days',
                dataMb: 5120,
                isUnlimited: false,
                validityDays: 15,
                costMinor: 950,
                currency: 'USD',
                countryIso2: ['DE', 'FR', 'IT', 'ES'],
            ),
        ];
    }

    private function applyFailureScenarios(string $operation, ?string $idempotencyKey = null, string $planId = 'fake-tr-10gb'): void
    {
        $scenario = $this->scenario();

        if ($scenario === 'unusable_credentials') {
            $this->record($operation, false, error: 'unusable_credentials');
            throw ProviderUnusableCredentialsException::missing($this->code());
        }

        if ($scenario === 'unavailable') {
            $this->record($operation, false, error: 'unavailable');
            throw ProviderUnavailableException::temporarily($this->code());
        }

        if ($scenario === 'rejected') {
            $this->record($operation, false, error: 'rejected');
            throw ProviderRejectedException::because('Simulated provider rejection.', $this->code());
        }

        if ($scenario === 'timeout') {
            if ($idempotencyKey !== null) {
                $this->rememberPurchase($idempotencyKey, $planId);
            }
            $this->record($operation, false, error: 'timeout');
            throw ProviderTimeoutException::timedOut($this->code());
        }

        if ($scenario === 'timeout_missing') {
            $this->record($operation, false, error: 'timeout');
            throw ProviderTimeoutException::timedOut($this->code());
        }
    }

    private function rememberPurchase(string $idempotencyKey, string $planId): ProviderPurchaseResult
    {
        $orderId = 'fake-order-'.$this->token($idempotencyKey);
        $profileId = 'fake-esim-'.$this->token($idempotencyKey);

        Cache::put($this->cacheKey($idempotencyKey), [
            'order_id' => $orderId,
            'profile_id' => $profileId,
            'plan_id' => $planId,
        ], 3600);

        return $this->purchaseResult($orderId, $profileId, $planId);
    }

    private function purchaseResult(string $orderId, string $profileId, string $planId): ProviderPurchaseResult
    {
        return new ProviderPurchaseResult(
            externalOrderId: $orderId,
            status: 'completed',
            fulfillment: $this->fulfillment($profileId, $planId),
        );
    }

    private function fulfillment(string $profileId, string $planId): EsimFulfillmentResult
    {
        $plan = null;
        foreach ($this->catalog() as $candidate) {
            if ($candidate->externalPlanId === $planId) {
                $plan = $candidate;
                break;
            }
        }

        $token = strtoupper(substr(md5($profileId), 0, 12));

        return new EsimFulfillmentResult(
            externalProfileId: $profileId,
            iccid: '890000'.substr(preg_replace('/\D/', '', md5($profileId)) ?? '1234567890123', 0, 13),
            matchingId: $token,
            smdpAddress: 'smdp.fake.local',
            activationCode: 'LPA:1$smdp.fake.local$'.$token,
            dataTotalMb: $plan?->dataMb ?? 10240,
            dataRemainingMb: $plan?->dataMb ?? 10240,
            expiresAt: new DateTimeImmutable('+30 days'),
        );
    }

    private function scenario(): string
    {
        $metadata = $this->provider?->metadata ?? [];

        return (string) ($metadata['scenario'] ?? 'success');
    }

    private function token(string $value): string
    {
        return substr(md5($value), 0, 8);
    }

    private function cacheKey(string $idempotencyKey): string
    {
        return 'fake-provider-purchase:'.$idempotencyKey;
    }

    private function record(string $operation, bool $success, ?int $ms = 3, ?string $error = null): void
    {
        if ($this->provider === null) {
            return;
        }

        $this->logger->record(
            provider: $this->provider,
            operation: $operation,
            success: $success,
            durationMs: $ms,
            errorMessage: $error,
        );
    }
}
