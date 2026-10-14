<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;

final class TemplateWebhookVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(?string $signature, array $payload): bool
    {
        throw ProviderNotImplementedException::untilOfficialDocs('verifyInboundSignature', 'template');
    }
}
