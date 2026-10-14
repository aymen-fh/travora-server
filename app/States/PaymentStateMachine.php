<?php

declare(strict_types=1);

namespace App\States;

use App\Enums\OrderPaymentStatus;
use App\Exceptions\InvalidStateTransitionException;

final class PaymentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'pending' => ['partially_paid', 'paid', 'failed', 'refunded'],
        'partially_paid' => ['paid', 'failed', 'partially_refunded'],
        'paid' => ['refunded', 'partially_refunded'],
        'failed' => ['pending'],
        'refunded' => [],
        'partially_refunded' => ['refunded'],
    ];

    public static function assert(OrderPaymentStatus $from, OrderPaymentStatus $to): void
    {
        if (! self::can($from, $to)) {
            throw InvalidStateTransitionException::because('order_payment', $from->value, $to->value);
        }
    }

    public static function can(OrderPaymentStatus $from, OrderPaymentStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value] ?? [], true);
    }
}
