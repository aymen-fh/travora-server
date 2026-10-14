<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Provider;
use App\Services\Providers\AcceptProviderWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, AcceptProviderWebhook $accept): JsonResponse
    {
        $model = Provider::query()->where('code', $provider)->firstOrFail();
        $payload = $request->all();
        $eventId = isset($payload['event_id']) ? (string) $payload['event_id'] : (isset($payload['id']) ? (string) $payload['id'] : null);
        $eventType = (string) ($payload['event_type'] ?? $payload['type'] ?? $payload['event'] ?? 'unknown');

        $event = $accept(
            $model,
            $eventType,
            $payload,
            $eventId,
            $request->header('X-Signature') ?? $request->header('X-Webhook-Signature'),
        );

        return ApiResponse::success(['id' => $event->id], 'Accepted', 202);
    }
}
