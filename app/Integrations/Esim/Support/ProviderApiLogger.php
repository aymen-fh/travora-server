<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

use App\Models\Provider;
use App\Models\ProviderApiLog;
use Illuminate\Support\Str;

final class ProviderApiLogger
{
    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    public function record(
        Provider $provider,
        string $operation,
        bool $success,
        ?int $durationMs = null,
        ?string $errorMessage = null,
        ?array $request = null,
        ?array $response = null,
    ): void {
        $level = (string) config('esim.logging.level', 'basic');

        if ($level === 'off') {
            return;
        }

        $storeBodies = $level === 'full' && ! app()->environment('production');

        $log = new ProviderApiLog;
        $log->provider_id = $provider->id;
        $log->service_type = 'esim';
        $log->direction = 'outbound';
        $log->endpoint = $operation;
        $log->method = null;
        $log->status_code = null;
        $log->duration_ms = $durationMs;
        $log->correlation_id = (string) Str::uuid();
        $log->request_body = $storeBodies && $request !== null ? SecretMasker::mask($request) : null;
        $log->response_body = $storeBodies && $response !== null ? SecretMasker::mask($response) : null;
        $log->error_message = $errorMessage;
        $log->save();
    }
}
