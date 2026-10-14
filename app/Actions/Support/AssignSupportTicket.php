<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Actions\Audit\RecordAuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;

final class AssignSupportTicket
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, SupportTicket $ticket, string $assigneeId, ?Request $request = null): SupportTicket
    {
        $old = ['assigned_to' => $ticket->assigned_to];
        $ticket->assigned_to = $assigneeId;
        $ticket->save();

        ($this->audit)(
            'support.ticket.assigned',
            $actor,
            SupportTicket::class,
            $ticket->id,
            $old,
            ['assigned_to' => $assigneeId],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $ticket->fresh(['user:id,email', 'assignee:id,email', 'messages']) ?? $ticket;
    }
}
