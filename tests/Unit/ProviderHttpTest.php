<?php

declare(strict_types=1);

use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Integrations\Esim\Support\ProviderHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('maps connection timeouts without interpreting vendor status codes', function (): void {
    Http::fake(function (): never {
        throw new ConnectionException('Connection timed out');
    });

    app(ProviderHttp::class)->send('fake', 'GET', 'https://example.test/');
})->throws(ProviderTimeoutException::class);

it('returns the raw HTTP response so drivers can map official docs later', function (): void {
    Http::fake([
        'https://example.test/' => Http::response(['ok' => true], 200),
    ]);

    $response = app(ProviderHttp::class)->send('fake', 'GET', 'https://example.test/');

    expect($response->successful())->toBeTrue();
    expect($response->json('ok'))->toBeTrue();
});

it('does not treat HTTP 429 as a Travora timeout', function (): void {
    Http::fake([
        'https://example.test/rate' => Http::response(['error' => 'rate_limited'], 429),
    ]);

    $response = app(ProviderHttp::class)->send('fake', 'POST', 'https://example.test/rate');

    expect($response->status())->toBe(429);
    expect($response->json('error'))->toBe('rate_limited');
});

it('does not treat HTTP 500 as a successful provider result', function (): void {
    Http::fake([
        'https://example.test/down' => Http::response('internal error', 500),
    ]);

    $response = app(ProviderHttp::class)->send('fake', 'POST', 'https://example.test/down');

    expect($response->serverError())->toBeTrue();
    expect($response->successful())->toBeFalse();
});
