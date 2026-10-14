<?php

declare(strict_types=1);

use App\Enums\ProviderCapability;
use App\Integrations\Esim\Drivers\EsimAccess\EsimAccessProvider;
use App\Integrations\Esim\Drivers\EsimAccess\EsimAccessSigner;
use App\Integrations\Esim\DTOs\PurchaseRequest;
use App\Integrations\Esim\DTOs\RenewRequest;
use App\Integrations\Esim\DTOs\TopUpRequest;
use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Integrations\Esim\Exceptions\ProviderRejectedException;
use App\Integrations\Esim\Exceptions\ProviderUnusableCredentialsException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Integrations\Esim\Support\EsimAccessWebhookVerifier;
use App\Models\Provider;
use App\Models\ProviderCredential;
use Illuminate\Support\Facades\Http;

function esimAccessDriver(): EsimAccessProvider
{
    $provider = Provider::factory()->esimAccess()->create();

    foreach (['access_code' => 'test-access-code', 'secret_key' => 'test-secret-key'] as $key => $value) {
        $credential = new ProviderCredential;
        $credential->provider_id = $provider->id;
        $credential->environment = 'test';
        $credential->credential_key = $key;
        $credential->secret_reference = $value;
        $credential->save();
    }

    $driver = app(ProviderManager::class)->driver($provider);
    expect($driver)->toBeInstanceOf(EsimAccessProvider::class);

    return $driver;
}

function officialEsimRow(): array
{
    return [
        'esimTranNo' => '23120118156818',
        'orderNo' => 'B23051616050537',
        'transactionId' => 'idem-1',
        'iccid' => '8943108170000775671',
        'ac' => 'LPA:1$rsp-eu.redteamobile.com$43DE23C67EE747BCAD6B63E8B67B261F',
        'qrCodeUrl' => 'https://p.qrsim.net/example.png',
        'smdpStatus' => 'RELEASED',
        'expiredTime' => '2024-05-29T18:34:17+0000',
        'totalVolume' => 5368709120,
        'orderUsage' => 0,
        'esimStatus' => 'GOT_RESOURCE',
        'packageList' => [],
    ];
}

it('signs the official Partner HMAC example', function (): void {
    $headers = app(EsimAccessSigner::class)->headers(
        '11111',
        '1111',
        '{"imsi":"326543826"}',
        '1628670421',
        '4ce9d9cdac9e4e17b3a2c66c358c1ce2',
    );

    expect($headers['RT-AccessCode'])->toBe('11111');
    expect($headers['RT-Timestamp'])->toBe('1628670421');
    expect($headers['RT-RequestID'])->toBe('4ce9d9cdac9e4e17b3a2c66c358c1ce2');
    expect($headers['RT-Signature'])->toBe('7eb765e27df5373dea2dbc8c41a7d9557743e46c8054750f3d851b3fd01d0835');
});

it('resolves EsimAccessProvider through ProviderManager', function (): void {
    $driver = esimAccessDriver();

    expect($driver->code())->toBe('esimaccess');
    expect($driver->capabilities()->supports(ProviderCapability::Purchase))->toBeTrue();
    expect($driver->capabilities()->supports(ProviderCapability::Renewal))->toBeFalse();
    expect($driver->capabilities()->supports(ProviderCapability::Topup))->toBeTrue();
    expect($driver->capabilities()->supports(ProviderCapability::Webhooks))->toBeTrue();
});

it('refuses to operate without stored credential references', function (): void {
    $provider = Provider::factory()->esimAccess()->create();
    $driver = app(ProviderManager::class)->driver($provider);

    $driver->health();
})->throws(ProviderUnusableCredentialsException::class);

it('maps health from the official balance query', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/balance/query' => Http::response([
            'success' => true,
            'errorCode' => '0',
            'errorMsg' => null,
            'obj' => ['balance' => 2230180],
        ], 200),
    ]);

    $health = esimAccessDriver()->health();

    expect($health->status->value)->toBe('healthy');
    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && $request->hasHeader('RT-AccessCode')
            && $request->hasHeader('RT-Signature')
            && $request->hasHeader('RT-RequestID')
            && $request->hasHeader('RT-Timestamp');
    });
});

it('maps catalog packages into Travora plan units', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/package/list' => Http::response([
            'success' => true,
            'obj' => [
                'packageList' => [[
                    'packageCode' => 'JC063',
                    'slug' => 'JP_1_7',
                    'name' => 'Japan 1GB 7Days',
                    'price' => 7000,
                    'currencyCode' => 'USD',
                    'volume' => 1073741824,
                    'duration' => 7,
                    'durationUnit' => 'DAY',
                    'location' => 'JP',
                    'dataType' => 1,
                    'description' => 'Japan 1GB 7Days',
                ]],
            ],
        ], 200),
    ]);

    $plans = esimAccessDriver()->getPlans();

    expect($plans)->toHaveCount(1);
    expect($plans[0]->externalPlanId)->toBe('JP_1_7');
    expect($plans[0]->dataMb)->toBe(1024);
    expect($plans[0]->costMinor)->toBe(70);
    expect($plans[0]->countryIso2)->toBe(['JP']);
});

it('returns a pending purchase then completes via findPurchase without ordering again', function (): void {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/esim/order')) {
            return Http::response([
                'success' => true,
                'obj' => ['orderNo' => 'B23051616050537', 'transactionId' => 'idem-1'],
            ], 200);
        }

        if (str_contains($url, '/esim/query')) {
            return Http::response([
                'success' => true,
                'obj' => ['esimList' => [officialEsimRow()], 'pager' => ['pageNum' => 1, 'pageSize' => 50, 'total' => 1]],
            ], 200);
        }

        return Http::response(['success' => false], 200);
    });

    $driver = esimAccessDriver();
    $first = $driver->purchase(new PurchaseRequest('JP_1_7', 'idem-1'));
    expect($first->externalOrderId)->toBe('B23051616050537');
    expect($first->isCompleted())->toBeTrue();
    expect($first->fulfillment?->externalProfileId)->toBe('23120118156818');
    expect($first->fulfillment?->smdpAddress)->toBe('rsp-eu.redteamobile.com');

    $found = $driver->findPurchase('idem-1');
    expect($found?->isCompleted())->toBeTrue();

    $orders = collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/esim/order'))
        ->count();
    expect($orders)->toBe(1);
});

it('treats official 200010 as pending allocation, not a second purchase', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/esim/order' => Http::response([
            'success' => true,
            'obj' => ['orderNo' => 'B23051616050537', 'transactionId' => 'idem-pending'],
        ], 200),
        'https://api.esimaccess.com/api/v1/open/esim/query' => Http::response([
            'success' => false,
            'errorCode' => '200010',
            'errorMsg' => 'Profile is being downloaded for the order.',
            'obj' => null,
        ], 200),
    ]);

    $result = esimAccessDriver()->purchase(new PurchaseRequest('JP_1_7', 'idem-pending'));

    expect($result->isCompleted())->toBeFalse();
    expect($result->status)->toBe('pending');
    expect($result->externalOrderId)->toBe('B23051616050537');
});

it('returns null from findPurchase when no order was remembered', function (): void {
    Http::fake();

    expect(esimAccessDriver()->findPurchase('never-ordered'))->toBeNull();
    Http::assertNothingSent();
});

it('maps usage bytes into EsimUsageSnapshot megabytes', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/esim/usage/query' => Http::response([
            'success' => true,
            'errorCode' => '0',
            'obj' => [
                'esimUsageList' => [[
                    'esimTranNo' => '25031120490003',
                    'dataUsage' => 1453344832,
                    'totalData' => 5368709120,
                    'lastUpdateTime' => '2025-03-19T18:00:00+0000',
                ]],
            ],
        ], 200),
    ]);

    $usage = esimAccessDriver()->getUsage('25031120490003');

    expect($usage->usedMb)->toBe(1386);
    expect($usage->remainingMb)->toBe(3734);
});

it('maps an official top-up response without using renew', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/esim/topup' => Http::response([
            'success' => true,
            'obj' => [
                'transactionId' => 'top-1',
                'iccid' => '89852245280001354019',
                'totalVolume' => 4294967296,
                'orderUsage' => 0,
                'topUpEsimTranNo' => '26040118050018',
            ],
        ], 200),
    ]);

    $top = esimAccessDriver()->topUp(new TopUpRequest('23120118156818', 'TOPUP_JC063', 'top-1'));

    expect($top->status)->toBe('completed');
    expect($top->externalReference)->toBe('26040118050018');
    expect($top->dataMbAdded)->toBe(4096);
});

it('does not implement renewal and does not call top-up for it', function (): void {
    Http::fake();

    expect(fn () => esimAccessDriver()->renew(new RenewRequest('23120118156818', 'JP_1_7', 'ren-1')))
        ->toThrow(ProviderNotImplementedException::class);

    Http::assertNothingSent();
});

it('maps DATA_USAGE webhooks and ignores other official notify types', function (): void {
    $driver = esimAccessDriver();

    expect($driver->interpretInboundEvent([
        'notifyType' => 'ORDER_STATUS',
        'notifyId' => 'abc',
        'content' => ['transactionId' => 'x'],
    ]))->toBeNull();

    $usage = $driver->interpretInboundEvent([
        'notifyType' => 'DATA_USAGE',
        'notifyId' => '7cb37a1e9ed2468e9243e6ccae682125',
        'content' => [
            'esimTranNo' => '26041615170028',
            'orderUsage' => 10741066164,
            'remain' => 10733770316,
        ],
    ]);

    expect($usage?->type)->toBe('usage');
    expect($usage?->externalProfileId)->toBe('26041615170028');
    expect($usage?->usage?->usedMb)->toBe(10243);
});

it('accepts the official unsigned webhook envelope and rejects a missing notifyId', function (): void {
    $verifier = new EsimAccessWebhookVerifier;

    expect($verifier->verify(null, [
        'notifyType' => 'DATA_USAGE',
        'notifyId' => '7cb37a1e9ed2468e9243e6ccae682125',
        'content' => [],
    ]))->toBeTrue();

    expect($verifier->verify(null, ['notifyType' => 'DATA_USAGE']))->toBeFalse();
});

it('maps insufficient balance to a provider rejection', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/esim/order' => Http::response([
            'success' => false,
            'errorCode' => '200007',
            'errorMsg' => 'Insufficient account balance',
            'obj' => null,
        ], 200),
    ]);

    esimAccessDriver()->purchase(new PurchaseRequest('JP_1_7', 'idem-balance'));
})->throws(ProviderRejectedException::class);

it('cancels, suspends, and texts a profile on the official paths', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/esim/*' => Http::response([
            'success' => true,
            'errorCode' => '0',
            'obj' => [],
        ], 200),
    ]);

    $driver = esimAccessDriver();
    $driver->cancelProfile('23120118156818');
    $driver->suspendProfile('23120118156818');
    $driver->sendSms('23120118156818', 'Your Message!');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.esimaccess.com/api/v1/open/esim/cancel'
        && $request['esimTranNo'] === '23120118156818');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.esimaccess.com/api/v1/open/esim/sendSms'
        && $request['message'] === 'Your Message!');
});

it('reads the merchant balance as the official scaled integer', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/balance/query' => Http::response([
            'success' => true,
            'obj' => ['balance' => 100000],
        ], 200),
    ]);

    expect(esimAccessDriver()->merchantBalance())->toBe(100000);
});
