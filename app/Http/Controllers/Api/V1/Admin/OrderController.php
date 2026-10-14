<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Orders\CancelOrderForAdmin;
use App\Actions\Orders\RetryOrderFulfillment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $page = Order::query()
            ->with(['user:id,email', 'items'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->string('user_id')))
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('order_number', 'like', '%'.$search.'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('reconciliation_status'), fn ($query) => $query->where('metadata->reconciliation_status', $request->string('reconciliation_status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminOrderResource::collection($page->getCollection())->resolve(),
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

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success(new AdminOrderResource($order->load(['user:id,email', 'items.esimProfile'])));
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrderForAdmin $cancel): JsonResponse
    {
        $this->authorize('cancel', $order);

        return ApiResponse::success(
            new AdminOrderResource(($cancel)($request->user(), $order, $request->string('reason')->toString(), $request)->load('user:id,email')),
            'Order cancelled',
        );
    }

    public function retryFulfillment(Request $request, Order $order, RetryOrderFulfillment $retry): JsonResponse
    {
        $this->authorize('retryFulfillment', $order);

        return ApiResponse::success(
            new AdminOrderResource(($retry)($request->user(), $order, $request)->load('user:id,email')),
            'Fulfillment reconciled',
        );
    }
}
