<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Models\Provider;

final class WebhookSignatureGuard
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(Provider $provider, ?string $signature, array $payload): bool
    {
        $class = config('esim.webhook_verifiers.'.$provider->code);

        if (! is_string($class) || ! class_exists($class)) {
            return false;
        }

        $verifier = app($class);

        if (! method_exists($verifier, 'verify')) {
            return false;
        }

        try {
            return (bool) $verifier->verify($signature, $payload);
        } catch (ProviderNotImplementedException) {
            return false;
        }
    }
}
