<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Support\AssignSupportTicket;
use App\Actions\Support\CloseSupportTicket;
use App\Actions\Support\ReplyToSupportTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignSupportTicketRequest;
use App\Http\Requests\Admin\CloseSupportTicketRequest;
use App\Http\Requests\Admin\ReplySupportTicketRequest;
use App\Http\Resources\Admin\AdminSupportTicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupportTicket::class);

        $page = SupportTicket::query()
            ->with(['user:id,email', 'assignee:id,email'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('ticket_number', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->string('user_id')))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->string('assigned_to')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminSupportTicketResource::collection($page->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        );
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return ApiResponse::success(
            new AdminSupportTicketResource($ticket->load(['user:id,email', 'assignee:id,email', 'messages'])),
        );
    }

    public function assign(AssignSupportTicketRequest $request, SupportTicket $ticket, AssignSupportTicket $assign): JsonResponse
    {
        $this->authorize('assign', $ticket);

        return ApiResponse::success(
            new AdminSupportTicketResource(($assign)($request->user(), $ticket, $request->string('assigned_to')->toString(), $request)),
            'Ticket assigned',
        );
    }

    public function message(ReplySupportTicketRequest $request, SupportTicket $ticket, ReplyToSupportTicket $reply): JsonResponse
    {
        $this->authorize('reply', $ticket);

        return ApiResponse::success(
            new AdminSupportTicketResource(($reply)($request->user(), $ticket, $request->string('body')->toString(), $request)),
            'Reply sent',
        );
    }

    public function close(CloseSupportTicketRequest $request, SupportTicket $ticket, CloseSupportTicket $close): JsonResponse
    {
        $this->authorize('close', $ticket);

        return ApiResponse::success(
            new AdminSupportTicketResource(($close)($request->user(), $ticket, $request->string('reason')->toString(), $request)),
            'Ticket closed',
        );
    }
}
