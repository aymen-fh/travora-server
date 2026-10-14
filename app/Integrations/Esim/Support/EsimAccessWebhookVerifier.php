<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

/**
 * Official Partner webhooks are unsigned JSON POSTs (docs.esimaccess.com Webhooks folder).
 * There is no documented inbound HMAC. Envelope fields notifyType + notifyId are required.
 */
final class EsimAccessWebhookVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(?string $signature, array $payload): bool
    {
        $type = $payload['notifyType'] ?? null;
        $id = $payload['notifyId'] ?? null;

        return is_string($type) && $type !== '' && is_string($id) && $id !== '';
    }
}
