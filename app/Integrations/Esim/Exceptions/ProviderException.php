<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Exceptions;

use App\Integrations\Esim\DTOs\ProviderErrorDTO;
use RuntimeException;

class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ProviderErrorDTO $error,
        public readonly ?string $providerCode = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromError(ProviderErrorDTO $error, ?string $providerCode = null): self
    {
        return new self($error->message, $error, $providerCode);
    }

    public function httpStatus(): int
    {
        return 502;
    }
}
