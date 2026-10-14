<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicket
 */
final class AdminSupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'user_id' => $this->user_id,
            'assigned_to' => $this->assigned_to,
            'category' => $this->category,
            'subject' => $this->subject,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'email' => $this->user?->email,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'email' => $this->assignee?->email,
            ]),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn (SupportMessage $message): array => [
                'id' => $message->id,
                'sender_user_id' => $message->sender_user_id,
                'sender_type' => $message->sender_type,
                'message' => $message->message,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
