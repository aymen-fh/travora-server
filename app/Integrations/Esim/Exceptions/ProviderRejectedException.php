<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Exceptions;

use App\Integrations\Esim\DTOs\ProviderErrorDTO;

final class ProviderRejectedException extends ProviderException
{
    public static function because(string $message = 'Provider rejected the operation.', ?string $providerCode = null): self
    {
        return new self(
            $message,
            new ProviderErrorDTO('rejected', $message, false),
            $providerCode,
        );
    }
}
