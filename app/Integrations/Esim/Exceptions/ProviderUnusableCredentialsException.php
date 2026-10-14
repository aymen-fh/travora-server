<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Exceptions;

use App\Integrations\Esim\DTOs\ProviderErrorDTO;

final class ProviderUnusableCredentialsException extends ProviderException
{
    public static function missing(?string $providerCode = null): self
    {
        return new self(
            'Provider credentials are missing or unusable.',
            new ProviderErrorDTO('unusable_credentials', 'Provider credentials are missing or unusable.', false),
            $providerCode,
        );
    }
}
