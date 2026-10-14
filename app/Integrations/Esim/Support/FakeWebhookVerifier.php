<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

final class FakeWebhookVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(?string $signature, array $payload): bool
    {
        if (($payload['travora_fixture'] ?? null) !== null) {
            return true;
        }

        return $signature === 'travora-fixture';
    }
}
