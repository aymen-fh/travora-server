<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Providers\RetryWebhookEvent;
use App\Enums\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminWebhookEventResource;
use App\Http\Responses\ApiResponse;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebhookEvent::class);

        $page = WebhookEvent::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('provider_id'), fn ($query) => $query->where('provider_id', $request->string('provider_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminWebhookEventResource::collection($page->getCollection())->resolve(),
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

    public function deadLetter(Request $request): JsonResponse
    {
        $this->authorize('viewDeadLetter', WebhookEvent::class);

        $page = WebhookEvent::query()
            ->where('status', WebhookEventStatus::Failed)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminWebhookEventResource::collection($page->getCollection())->resolve(),
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

    public function show(WebhookEvent $webhookEvent): JsonResponse
    {
        $this->authorize('view', $webhookEvent);

        return ApiResponse::success(new AdminWebhookEventResource($webhookEvent));
    }

    public function retry(Request $request, WebhookEvent $webhookEvent, RetryWebhookEvent $retry, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('retry', $webhookEvent);

        $event = $retry($webhookEvent);

        $audit(
            'webhook.retried',
            $request->user(),
            WebhookEvent::class,
            $event->id,
            null,
            ['status' => $event->status?->value],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(new AdminWebhookEventResource($event), 'Retry queued');
    }
}
