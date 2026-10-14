<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Exceptions;

use App\Integrations\Esim\DTOs\ProviderErrorDTO;

final class ProviderNotImplementedException extends ProviderException
{
    public static function untilOfficialDocs(string $operation, ?string $providerCode = null): self
    {
        return new self(
            "[{$operation}] waits for official provider documentation.",
            new ProviderErrorDTO('not_implemented', "[{$operation}] waits for official provider documentation.", false),
            $providerCode,
        );
    }
}
