<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\EsimPlan;
use App\Models\Order;
use App\Services\Orders\CheckoutEsimOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $page = Order::query()
            ->ownedBy($request->user()->id)
            ->with(['items.esimProfile'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            OrderResource::collection($page->getCollection())->resolve(),
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

    public function store(StoreOrderRequest $request, CheckoutEsimOrder $checkout): JsonResponse
    {
        $this->authorize('create', Order::class);

        $plan = EsimPlan::query()->findOrFail($request->string('plan_id')->toString());
        $wallet = $request->user()->wallets()->where('currency', 'LYD')->firstOrFail();
        $key = $request->header('Idempotency-Key') ?: (string) Str::uuid();

        $order = $checkout(
            $request->user(),
            $plan,
            $wallet,
            $key,
            $request->integer('quantity', 1),
        );

        return ApiResponse::created(new OrderResource($order->load(['items.esimProfile'])), 'Order created');
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success(new OrderResource($order->load(['items.esimProfile'])));
    }

    public function cancel(Request $request, Order $order, CancelOrder $cancel, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('cancel', $order);

        $cancelled = ($cancel)($request->user(), $order)->load(['items.esimProfile']);

        $audit(
            'order.cancelled',
            $request->user(),
            Order::class,
            $order->id,
            null,
            ['status' => $cancelled->status?->value],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(new OrderResource($cancelled));
    }
}
