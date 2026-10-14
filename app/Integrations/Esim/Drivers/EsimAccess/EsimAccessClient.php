<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\EsimAccess;

use App\Integrations\Esim\Exceptions\ProviderMalformedException;
use App\Integrations\Esim\Support\ProviderHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

final class EsimAccessClient
{
    public function __construct(
        private readonly ProviderHttp $http,
        private readonly EsimAccessSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $payload  null sends an empty body (official balance first-call)
     * @return array<string, mixed>
     */
    public function post(string $providerCode, string $path, string $accessCode, string $secretKey, ?array $payload): array
    {
        if (! str_starts_with($path, '/api/v1/open/') || str_contains($path, '..')) {
            throw new InvalidArgumentException("Invalid eSIM Access path: {$path}");
        }

        $baseUrl = rtrim((string) config('esim.esimaccess.base_url', 'https://api.esimaccess.com'), '/');
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $allowedHosts = ['api.esimaccess.com', 'localhost', '127.0.0.1'];
        if ($host !== null && ! in_array($host, $allowedHosts, true) && ! app()->environment('testing')) {
            throw new InvalidArgumentException("Disallowed eSIM Access host: {$host}");
        }

        $this->throttle();

        $body = $payload === null ? '' : json_encode($payload === [] ? new \stdClass() : $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $url = $baseUrl.$path;
        $headers = $this->signer->headers($accessCode, $secretKey, $body);

        $options = [
            'headers' => $headers,
            'body' => $body,
        ];

        if ($body !== '') {
            $options['headers']['Content-Type'] = 'application/json';
        }

        $response = $this->http->send($providerCode, 'POST', $url, $options);

        return $this->decode($response, $providerCode);
    }

    private function throttle(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $executed = RateLimiter::attempt(
            'provider:esimaccess',
            8,
            fn () => true,
            1
        );

        if (! $executed) {
            usleep(125000);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $providerCode): array
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw ProviderMalformedException::result($providerCode);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
