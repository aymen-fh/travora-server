<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Payments\RefundPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundPaymentRequest;
use App\Http\Resources\Admin\AdminPaymentAttemptResource;
use App\Http\Resources\Admin\AdminPaymentRefundResource;
use App\Http\Resources\Admin\AdminPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $page = Payment::query()
            ->with(['user:id,email', 'order:id,order_number'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('payment_number', 'like', '%'.$search.'%')
                        ->orWhere('provider_reference', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->string('user_id')))
            ->when($request->filled('order_id'), fn ($query) => $query->where('order_id', $request->string('order_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminPaymentResource::collection($page->getCollection())->resolve(),
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

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return ApiResponse::success(new AdminPaymentResource($payment->load(['user:id,email', 'order:id,order_number', 'paymentMethod:id,type'])));
    }

    public function attempts(Payment $payment): JsonResponse
    {
        $this->authorize('viewAttempts', $payment);

        return ApiResponse::success(
            AdminPaymentAttemptResource::collection($payment->attempts()->orderBy('attempt_number')->get()),
        );
    }

    public function storeRefund(RefundPaymentRequest $request, Payment $payment, RefundPayment $refund): JsonResponse
    {
        $this->authorize('refund', $payment);

        $amount = $request->integer('amount_minor') ?: ($payment->amount_minor - $payment->refunded_minor);
        $created = $refund(
            $request->user(),
            $payment,
            $amount,
            $request->string('reason')->toString(),
            (string) $request->header('Idempotency-Key'),
            $request,
        );

        return ApiResponse::success(new AdminPaymentRefundResource($created), 'Refund recorded');
    }

    public function refunds(Payment $payment): JsonResponse
    {
        $this->authorize('viewRefunds', $payment);

        return ApiResponse::success(
            AdminPaymentRefundResource::collection($payment->refunds()->latest()->get()),
        );
    }

    public function refundIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentRefund::class);

        $page = PaymentRefund::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_id'), fn ($query) => $query->where('payment_id', $request->string('payment_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminPaymentRefundResource::collection($page->getCollection())->resolve(),
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
}
