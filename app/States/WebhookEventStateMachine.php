<?php

declare(strict_types=1);

namespace App\States;

use App\Enums\WebhookEventStatus;
use App\Exceptions\InvalidStateTransitionException;

final class WebhookEventStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'pending' => ['processing', 'ignored', 'failed'],
        'processing' => ['processed', 'failed', 'ignored', 'pending'],
        'processed' => [],
        'ignored' => [],
        'failed' => ['pending', 'processing'],
    ];

    public static function assert(WebhookEventStatus $from, WebhookEventStatus $to): void
    {
        if (! self::can($from, $to)) {
            throw InvalidStateTransitionException::because('webhook_event', $from->value, $to->value);
        }
    }

    public static function can(WebhookEventStatus $from, WebhookEventStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value] ?? [], true);
    }
}
