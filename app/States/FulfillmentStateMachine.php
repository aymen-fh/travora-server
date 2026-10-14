<?php

declare(strict_types=1);

namespace App\States;

use App\Enums\FulfillmentStatus;
use App\Exceptions\InvalidStateTransitionException;

final class FulfillmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'pending' => ['processing', 'cancelled', 'failed'],
        'processing' => ['completed', 'partially_completed', 'failed', 'cancelled'],
        'partially_completed' => ['completed', 'failed', 'cancelled'],
        'completed' => [],
        'failed' => ['pending', 'cancelled'],
        'cancelled' => [],
    ];

    public static function assert(FulfillmentStatus $from, FulfillmentStatus $to): void
    {
        if (! self::can($from, $to)) {
            throw InvalidStateTransitionException::because('fulfillment', $from->value, $to->value);
        }
    }

    public static function can(FulfillmentStatus $from, FulfillmentStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value] ?? [], true);
    }
}
