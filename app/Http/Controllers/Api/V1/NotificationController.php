<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $data = collect($page->items())->map(fn (Notification $item): array => [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'body' => $item->body,
            'read_at' => $item->read_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success($data, 'OK', 200, [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->read_at = now();
        $notification->save();

        return ApiResponse::success(null, 'Read');
    }
}
