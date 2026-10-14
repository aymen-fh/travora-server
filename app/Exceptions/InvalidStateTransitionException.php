<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $entity,
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct("Cannot transition {$entity} from {$from} to {$to}.");
    }

    public static function because(string $entity, string $from, string $to): self
    {
        return new self($entity, $from, $to);
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'INVALID_STATE_TRANSITION';
    }

    public function context(): array
    {
        return [
            'entity' => $this->entity,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
