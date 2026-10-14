<?php

declare(strict_types=1);

namespace App\States;

use App\Enums\EsimProfileStatus;
use App\Exceptions\InvalidStateTransitionException;

final class EsimStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'purchased' => ['provisioning', 'cancelled', 'failed'],
        'provisioning' => ['ready', 'failed', 'cancelled'],
        'ready' => ['installed', 'activated', 'suspended', 'expired', 'cancelled'],
        'installed' => ['activated', 'suspended', 'expired', 'cancelled'],
        'activated' => ['suspended', 'expired', 'cancelled'],
        'suspended' => ['activated', 'expired', 'cancelled'],
        'expired' => [],
        'cancelled' => [],
        'failed' => ['provisioning', 'cancelled'],
    ];

    public static function assert(EsimProfileStatus $from, EsimProfileStatus $to): void
    {
        if (! self::can($from, $to)) {
            throw InvalidStateTransitionException::because('esim_profile', $from->value, $to->value);
        }
    }

    public static function can(EsimProfileStatus $from, EsimProfileStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value] ?? [], true);
    }
}
