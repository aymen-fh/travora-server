<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;

final class CloseSupportTicket
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, SupportTicket $ticket, string $reason, ?Request $request = null): SupportTicket
    {
        if ($ticket->status === SupportTicketStatus::Closed) {
            return $ticket->fresh(['user:id,email', 'assignee:id,email', 'messages']) ?? $ticket;
        }

        $old = ['status' => $ticket->status?->value];
        $ticket->status = SupportTicketStatus::Closed;
        $ticket->closed_at = now();
        $ticket->save();

        ($this->audit)(
            'support.ticket.closed',
            $actor,
            SupportTicket::class,
            $ticket->id,
            $old,
            ['status' => SupportTicketStatus::Closed->value, 'reason' => $reason],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $ticket->fresh(['user:id,email', 'assignee:id,email', 'messages']) ?? $ticket;
    }
}
