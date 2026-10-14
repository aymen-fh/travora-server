<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Timeout-only HTTP helper for future official driver Clients.
 * Status/auth/signature mapping belongs in the driver after real docs exist.
 */
final class ProviderHttp
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function send(string $providerCode, string $method, string $url, array $options = []): Response
    {
        try {
            return Http::timeout((int) config('esim.timeout', 20))
                ->send($method, $url, $options);
        } catch (ConnectionException) {
            throw ProviderTimeoutException::timedOut($providerCode);
        }
    }
}
