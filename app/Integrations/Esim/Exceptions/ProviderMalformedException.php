<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Exceptions;

use App\Integrations\Esim\DTOs\ProviderErrorDTO;

final class ProviderMalformedException extends ProviderException
{
    public static function result(?string $providerCode = null): self
    {
        return new self(
            'Provider returned a result Travora cannot apply.',
            new ProviderErrorDTO('malformed', 'Provider returned a result Travora cannot apply.', false),
            $providerCode,
        );
    }
}
