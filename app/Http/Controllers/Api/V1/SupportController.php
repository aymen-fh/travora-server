<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $data = collect($page->items())->map(fn (SupportTicket $ticket): array => $this->ticketPayload($ticket));

        return ApiResponse::success($data, 'OK', 200, [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = new SupportTicket;
        $ticket->user_id = $request->user()->id;
        $ticket->ticket_number = 'SUP-'.strtoupper(Str::random(8));
        $ticket->category = $request->string('category', 'general')->toString() ?: 'general';
        $ticket->subject = $request->string('subject')->toString();
        $ticket->status = SupportTicketStatus::Open;
        $ticket->priority = SupportPriority::tryFrom($request->string('priority')->toString()) ?? SupportPriority::Normal;
        $ticket->last_message_at = now();
        $ticket->save();

        $message = new SupportMessage;
        $message->ticket_id = $ticket->id;
        $message->sender_user_id = $request->user()->id;
        $message->sender_type = 'customer';
        $message->message = $request->string('body')->toString();
        $message->save();

        return ApiResponse::created($this->ticketPayload($ticket->load('messages')), 'Ticket created');
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        return ApiResponse::success($this->ticketPayload($ticket->load('messages')));
    }

    public function message(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = new SupportMessage;
        $message->ticket_id = $ticket->id;
        $message->sender_user_id = $request->user()->id;
        $message->sender_type = 'customer';
        $message->message = $request->string('body')->toString();
        $message->save();

        $ticket->last_message_at = now();
        $ticket->save();

        return ApiResponse::created([
            'id' => $message->id,
            'message' => $message->message,
            'sender_type' => $message->sender_type,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'subject' => $ticket->subject,
            'status' => $ticket->status?->value,
            'priority' => $ticket->priority?->value,
            'messages' => $ticket->relationLoaded('messages')
                ? $ticket->messages->map(fn (SupportMessage $message): array => [
                    'id' => $message->id,
                    'sender_type' => $message->sender_type,
                    'message' => $message->message,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }
}
