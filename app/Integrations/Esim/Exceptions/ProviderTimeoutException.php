<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Exceptions;

use App\Integrations\Esim\DTOs\ProviderErrorDTO;

final class ProviderTimeoutException extends ProviderException
{
    public static function timedOut(?string $providerCode = null): self
    {
        return new self(
            'Provider operation timed out.',
            new ProviderErrorDTO('timeout', 'Provider operation timed out.', true),
            $providerCode,
        );
    }

    public function httpStatus(): int
    {
        return 504;
    }
}
