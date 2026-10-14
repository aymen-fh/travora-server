<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\EsimAccess;

use App\Domain\EsimFulfillmentResult;
use App\Domain\EsimUsageSnapshot;
use App\Enums\HealthStatus;
use App\Integrations\Esim\Contracts\EsimProvider;
use App\Integrations\Esim\Contracts\PartnerEsimOperations;
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
use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Integrations\Esim\Exceptions\ProviderRejectedException;
use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Integrations\Esim\Exceptions\ProviderUnavailableException;
use App\Integrations\Esim\Exceptions\ProviderUnusableCredentialsException;
use App\Integrations\Esim\Support\ProviderApiLogger;
use App\Integrations\Esim\Support\ProviderCapabilities;
use App\Integrations\Esim\Support\ProviderSecretStore;
use App\Models\Provider;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class EsimAccessProvider implements EsimProvider, PartnerEsimOperations
{
    private const CODE = 'esimaccess';

    private ?Provider $provider = null;

    public function __construct(
        private readonly EsimAccessClient $client,
        private readonly EsimAccessPlanMapper $plans,
        private readonly EsimAccessFulfillmentMapper $fulfillment,
        private readonly ProviderSecretStore $secrets,
        private readonly ProviderApiLogger $logger,
    ) {}

    public function code(): string
    {
        return self::CODE;
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
            renewal: false,
            webhooks: true,
        );
    }

    public function health(): ProviderHealthDTO
    {
        $started = hrtime(true);
        $payload = $this->request('/api/v1/open/balance/query', null, 'health');
        $ms = (int) ((hrtime(true) - $started) / 1_000_000);

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'health');
        }

        return new ProviderHealthDTO(HealthStatus::Healthy, max(1, $ms), 'eSIMAccess balance query succeeded');
    }

    public function getPlans(): array
    {
        return $this->listPlans([
            'locationCode' => '',
            'type' => 'BASE',
            'slug' => '',
            'packageCode' => '',
            'iccid' => '',
        ]);
    }

    public function getPlan(string $externalPlanId): ProviderPlanDTO
    {
        foreach ($this->listPlans([
            'locationCode' => '',
            'type' => '',
            'slug' => $externalPlanId,
            'packageCode' => $externalPlanId,
            'iccid' => '',
        ]) as $plan) {
            if ($plan->externalPlanId === $externalPlanId) {
                return $plan;
            }
        }

        $this->record('getPlan', false, error: 'unknown plan');
        throw ProviderRejectedException::because('Unknown plan.', $this->code());
    }

    public function getCoverage(): array
    {
        $payload = $this->request('/api/v1/open/location/list', ['locationCode' => ''], 'getCoverage');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'getCoverage');
        }

        $out = [];
        foreach ($this->obj($payload)['locationList'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mapped = $this->plans->coverage($row);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    public function purchase(PurchaseRequest $request): ProviderPurchaseResult
    {
        $body = [
            'transactionId' => $request->idempotencyKey,
            'packageInfoList' => [[
                'packageCode' => $request->externalPlanId,
                'count' => max(1, $request->quantity),
            ]],
        ];

        $payload = $this->request('/api/v1/open/esim/order', $body, 'purchase');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'purchase');
        }

        $orderNo = (string) ($this->obj($payload)['orderNo'] ?? '');
        if ($orderNo === '') {
            $this->record('purchase', false, error: 'malformed');
            throw ProviderMalformedException::result($this->code());
        }

        $this->rememberOrder($request->idempotencyKey, $orderNo);

        return $this->lookupOrder($orderNo, $request->idempotencyKey);
    }

    public function findPurchase(string $idempotencyKey): ?ProviderPurchaseResult
    {
        $orderNo = Cache::get($this->orderCacheKey($idempotencyKey));

        if (! is_string($orderNo) || $orderNo === '') {
            $this->record('findPurchase', false);

            return null;
        }

        try {
            $result = $this->lookupOrder($orderNo, $idempotencyKey, 'findPurchase');
        } catch (ProviderRejectedException) {
            $this->record('findPurchase', false);

            return null;
        }

        $this->record('findPurchase', true);

        return $result;
    }

    public function getEsim(string $externalProfileId): EsimFulfillmentResult
    {
        $esim = $this->queryProfile($externalProfileId, 'getEsim');
        $mapped = $this->fulfillment->fulfillment($esim);

        if ($mapped === null) {
            $this->record('getEsim', false, error: 'malformed');
            throw ProviderMalformedException::result($this->code());
        }

        $this->record('getEsim', true);

        return $mapped;
    }

    public function getUsage(string $externalProfileId): EsimUsageSnapshot
    {
        $payload = $this->request('/api/v1/open/esim/usage/query', [
            'esimTranNoList' => [$externalProfileId],
        ], 'getUsage');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'getUsage');
        }

        $row = $this->obj($payload)['esimUsageList'][0] ?? null;
        if (! is_array($row)) {
            $this->record('getUsage', false, error: 'malformed');
            throw ProviderMalformedException::result($this->code());
        }

        $used = $this->plans->bytesToMb((int) ($row['dataUsage'] ?? 0));
        $total = isset($row['totalData']) ? $this->plans->bytesToMb((int) $row['totalData']) : null;
        $remaining = $total === null ? null : max(0, $total - $used);

        $this->record('getUsage', true);

        return new EsimUsageSnapshot($used, $remaining, 'provider');
    }

    public function topUp(TopUpRequest $request): ProviderTopUpResult
    {
        $payload = $this->request('/api/v1/open/esim/topup', [
            'esimTranNo' => $request->externalProfileId,
            'iccid' => '',
            'packageCode' => $request->externalPlanId,
            'transactionId' => $request->idempotencyKey,
        ], 'topUp');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'topUp');
        }

        $obj = $this->obj($payload);
        $reference = (string) ($obj['topUpEsimTranNo'] ?? $obj['transactionId'] ?? $request->idempotencyKey);
        $total = isset($obj['totalVolume']) ? $this->plans->bytesToMb((int) $obj['totalVolume']) : null;
        $used = isset($obj['orderUsage']) ? $this->plans->bytesToMb((int) $obj['orderUsage']) : null;

        $this->record('topUp', true);

        return new ProviderTopUpResult(
            externalReference: $reference,
            status: 'completed',
            dataMbAdded: ($total !== null && $used !== null) ? max(0, $total - $used) : null,
        );
    }

    public function cancelProfile(string $esimTranNo): void
    {
        $this->acknowledge('/api/v1/open/esim/cancel', ['esimTranNo' => $esimTranNo], 'cancelProfile');
    }

    public function suspendProfile(string $esimTranNo): void
    {
        $this->acknowledge('/api/v1/open/esim/suspend', ['esimTranNo' => $esimTranNo], 'suspendProfile');
    }

    public function unsuspendProfile(string $esimTranNo): void
    {
        $this->acknowledge('/api/v1/open/esim/unsuspend', ['esimTranNo' => $esimTranNo], 'unsuspendProfile');
    }

    public function revokeProfile(string $esimTranNo): void
    {
        $this->acknowledge('/api/v1/open/esim/revoke', ['esimTranNo' => $esimTranNo], 'revokeProfile');
    }

    public function sendSms(string $esimTranNo, string $message): void
    {
        $this->acknowledge('/api/v1/open/esim/sendSms', [
            'esimTranNo' => $esimTranNo,
            'message' => $message,
        ], 'sendSms');
    }

    public function merchantBalance(): int
    {
        $payload = $this->request('/api/v1/open/balance/query', null, 'merchantBalance');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'merchantBalance');
        }

        $balance = $this->obj($payload)['balance'] ?? null;
        if (! is_numeric($balance)) {
            $this->record('merchantBalance', false, error: 'malformed');
            throw ProviderMalformedException::result($this->code());
        }

        $this->record('merchantBalance', true);

        return (int) $balance;
    }

    public function discountRules(string $discountRuleCode): array
    {
        $payload = $this->request('/api/v1/open/discountRule/list', [
            'discountRuleCode' => $discountRuleCode,
        ], 'discountRules');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'discountRules');
        }

        $this->record('discountRules', true);

        return $this->obj($payload);
    }

    public function saveWebhook(string $url): void
    {
        $this->acknowledge('/api/v1/open/webhook/save', ['webhook' => $url], 'saveWebhook');
    }

    public function queryWebhook(): array
    {
        $payload = $this->request('/api/v1/open/webhook/query', null, 'queryWebhook');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'queryWebhook');
        }

        $this->record('queryWebhook', true);

        return $this->obj($payload);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listRawPackages(array $filters = []): array
    {
        $payload = $this->request('/api/v1/open/package/list', array_merge([
            'locationCode' => '',
            'type' => '',
            'slug' => '',
            'packageCode' => '',
            'iccid' => '',
        ], $filters), 'listRawPackages');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'listRawPackages');
        }

        $list = $this->obj($payload)['packageList'] ?? [];

        $this->record('listRawPackages', true);

        return is_array($list) ? $list : [];
    }

    public function renew(RenewRequest $request): ProviderRenewResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('renew', $this->code());
    }

    public function interpretInboundEvent(array $payload): ?ProviderInboundEvent
    {
        if (($payload['notifyType'] ?? null) !== 'DATA_USAGE') {
            return null;
        }

        $content = $payload['content'] ?? [];
        if (! is_array($content)) {
            return null;
        }

        $profileId = (string) ($content['esimTranNo'] ?? '');
        if ($profileId === '') {
            return null;
        }

        $used = $this->plans->bytesToMb((int) ($content['orderUsage'] ?? 0));
        $remaining = isset($content['remain']) ? $this->plans->bytesToMb((int) $content['remain']) : null;

        return new ProviderInboundEvent(
            type: 'usage',
            externalProfileId: $profileId,
            usage: new EsimUsageSnapshot($used, $remaining, 'provider'),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<ProviderPlanDTO>
     */
    private function listPlans(array $filters): array
    {
        $payload = $this->request('/api/v1/open/package/list', $filters, 'getPlans');

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, 'getPlans');
        }

        $out = [];
        foreach ($this->obj($payload)['packageList'] ?? [] as $row) {
            if (is_array($row)) {
                $out[] = $this->plans->plan($row);
            }
        }

        $this->record('getPlans', true);

        return $out;
    }

    private function lookupOrder(string $orderNo, string $idempotencyKey, string $operation = 'purchase'): ProviderPurchaseResult
    {
        $payload = $this->request('/api/v1/open/esim/query', [
            'orderNo' => $orderNo,
            'iccid' => '',
            'pager' => ['pageNum' => 1, 'pageSize' => 50],
        ], $operation);

        if ($this->errorCode($payload) === '200010') {
            $this->record($operation, true);

            return new ProviderPurchaseResult($orderNo, 'pending');
        }

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, $operation);
        }

        $list = $this->obj($payload)['esimList'] ?? [];
        $esim = null;
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((string) ($row['transactionId'] ?? '') === $idempotencyKey || $esim === null) {
                $esim = $row;
                if ((string) ($row['transactionId'] ?? '') === $idempotencyKey) {
                    break;
                }
            }
        }
        $mapped = is_array($esim) ? $this->fulfillment->fulfillment($esim) : null;

        if ($mapped === null) {
            $this->record($operation, true);

            return new ProviderPurchaseResult($orderNo, 'pending');
        }

        $this->record($operation, true);

        return new ProviderPurchaseResult($orderNo, 'completed', $mapped);
    }

    /**
     * @return array<string, mixed>
     */
    private function queryProfile(string $esimTranNo, string $operation): array
    {
        $payload = $this->request('/api/v1/open/esim/query', [
            'esimTranNo' => $esimTranNo,
            'orderNo' => '',
            'iccid' => '',
            'pager' => ['pageNum' => 1, 'pageSize' => 50],
        ], $operation);

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, $operation);
        }

        $row = $this->obj($payload)['esimList'][0] ?? null;
        if (! is_array($row)) {
            $this->record($operation, false, error: 'malformed');
            throw ProviderMalformedException::result($this->code());
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $path, ?array $body, string $operation): array
    {
        [$access, $secret] = $this->credentials();

        try {
            return $this->client->post($this->code(), $path, $access, $secret, $body);
        } catch (ProviderMalformedException $exception) {
            $this->record($operation, false, error: 'malformed');
            throw $exception;
        } catch (Throwable $exception) {
            if ($exception instanceof ProviderTimeoutException) {
                $this->record($operation, false, error: 'timeout');
                throw $exception;
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(): array
    {
        if ($this->provider === null) {
            throw ProviderUnusableCredentialsException::missing($this->code());
        }

        $environment = app()->environment('testing') ? 'test' : app()->environment();
        $access = $this->secrets->reference($this->provider, 'access_code', $environment);
        $secret = $this->secrets->reference($this->provider, 'secret_key', $environment);

        if ($access === null || $access === '' || $secret === null || $secret === '') {
            throw ProviderUnusableCredentialsException::missing($this->code());
        }

        return [$access, $secret];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ok(array $payload): bool
    {
        return ($payload['success'] ?? false) === true || ($payload['success'] ?? null) === 'true';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function errorCode(array $payload): string
    {
        $code = $payload['errorCode'] ?? null;

        return $code === null ? '' : (string) $code;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function obj(array $payload): array
    {
        $obj = $payload['obj'] ?? [];

        return is_array($obj) ? $obj : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function throwFrom(array $payload, string $operation): never
    {
        $code = $this->errorCode($payload);
        $message = (string) ($payload['errorMessage'] ?? $payload['errorMsg'] ?? 'eSIMAccess error '.$code);

        $this->record($operation, false, error: $code !== '' ? $code : 'rejected');

        if (in_array($code, ['101003', '000101', '000102'], true)) {
            throw ProviderUnusableCredentialsException::missing($this->code());
        }

        if (in_array($code, ['101001', '000001', '900001', '101002'], true)) {
            throw ProviderUnavailableException::temporarily($this->code());
        }

        if ($code === '200002') {
            throw ProviderTimeoutException::timedOut($this->code());
        }

        throw ProviderRejectedException::because($message, $this->code());
    }

    private function rememberOrder(string $idempotencyKey, string $orderNo): void
    {
        Cache::put($this->orderCacheKey($idempotencyKey), $orderNo, 3600);
    }

    private function orderCacheKey(string $idempotencyKey): string
    {
        return 'esimaccess-order:'.($this->provider?->id ?? 'none').':'.$idempotencyKey;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function acknowledge(string $path, array $body, string $operation): void
    {
        $payload = $this->request($path, $body, $operation);

        if (! $this->ok($payload)) {
            $this->throwFrom($payload, $operation);
        }

        $this->record($operation, true);
    }

    private function record(string $operation, bool $success, ?string $error = null): void
    {
        if ($this->provider === null) {
            return;
        }

        $this->logger->record(
            provider: $this->provider,
            operation: $operation,
            success: $success,
            durationMs: 1,
            errorMessage: $error,
        );
    }
}
