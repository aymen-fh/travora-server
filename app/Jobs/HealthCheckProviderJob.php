<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Integrations\Esim\Exceptions\ProviderException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\Provider;
use App\Models\ProviderHealthCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class HealthCheckProviderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly string $providerId,
    ) {}

    public function handle(ProviderManager $manager): void
    {
        $provider = Provider::query()->find($this->providerId);
        if ($provider === null) {
            return;
        }

        try {
            $result = $manager->driver($provider)->health();
        } catch (InvalidArgumentException|ProviderException $exception) {
            Log::warning('Provider health check skipped', [
                'provider_id' => $this->providerId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $check = new ProviderHealthCheck;
        $check->provider_id = $provider->id;
        $check->service_type = 'esim';
        $check->status = $result->status;
        $check->latency_ms = $result->latencyMs;
        $check->message = $result->message;
        $check->checked_at = now();
        $check->save();

        $provider->health_status = $result->status;
        $provider->health_checked_at = now();
        $provider->save();
    }
}
