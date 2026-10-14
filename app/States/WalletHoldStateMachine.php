<?php

declare(strict_types=1);

namespace App\States;

use App\Enums\WalletHoldStatus;
use App\Exceptions\InvalidStateTransitionException;

final class WalletHoldStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'active' => ['captured', 'released', 'expired'],
        'captured' => [],
        'released' => [],
        'expired' => [],
    ];

    public static function assert(WalletHoldStatus $from, WalletHoldStatus $to): void
    {
        if (! self::can($from, $to)) {
            throw InvalidStateTransitionException::because('wallet_hold', $from->value, $to->value);
        }
    }

    public static function can(WalletHoldStatus $from, WalletHoldStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value] ?? [], true);
    }
}
