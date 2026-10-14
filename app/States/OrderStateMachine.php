<?php

declare(strict_types=1);

namespace App\States;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStateTransitionException;

final class OrderStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'cancelled', 'failed'],
        'confirmed' => ['processing', 'cancelled', 'failed'],
        'processing' => ['completed', 'failed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'failed' => ['pending', 'cancelled'],
    ];

    public static function assert(OrderStatus $from, OrderStatus $to): void
    {
        if (! self::can($from, $to)) {
            throw InvalidStateTransitionException::because('order', $from->value, $to->value);
        }
    }

    public static function can(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value] ?? [], true);
    }
}
