<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\SupportTicketStatus;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReplyToSupportTicket
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, SupportTicket $ticket, string $body, ?Request $request = null): SupportTicket
    {
        DB::transaction(function () use ($actor, $ticket, $body): void {
            $message = new SupportMessage;
            $message->ticket_id = $ticket->id;
            $message->sender_user_id = $actor->id;
            $message->sender_type = 'support';
            $message->message = $body;
            $message->save();

            $ticket->last_message_at = now();
            if ($ticket->status === SupportTicketStatus::Open || $ticket->status === SupportTicketStatus::PendingSupport) {
                $ticket->status = SupportTicketStatus::PendingCustomer;
            }
            $ticket->save();
        });

        ($this->audit)(
            'support.ticket.replied',
            $actor,
            SupportTicket::class,
            $ticket->id,
            null,
            ['sender_type' => 'support'],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $ticket->fresh(['user:id,email', 'assignee:id,email', 'messages']) ?? $ticket;
    }
}
