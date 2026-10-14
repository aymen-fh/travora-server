<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Exceptions;

use App\Integrations\Esim\DTOs\ProviderErrorDTO;

final class ProviderUnavailableException extends ProviderException
{
    public static function temporarily(?string $providerCode = null): self
    {
        return new self(
            'Provider is temporarily unavailable.',
            new ProviderErrorDTO('unavailable', 'Provider is temporarily unavailable.', true),
            $providerCode,
        );
    }
}
