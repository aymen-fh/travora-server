<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\SettlementInvariantException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentRefund;
use App\States\PaymentStateMachine;

/**
 * Single writer for payment allocations, payment refunds, orders.paid_minor, and payments.refunded_minor.
 * Must be called inside the caller's transaction after lockForUpdate() on the order and payment.
 * This service does not open, commit, or roll back a transaction.
 */
final class RecordOrderSettlement
{
    public function allocate(
        Order $order,
        Payment $payment,
        int $amountMinor,
        string $currency,
        string $sourceType,
        ?string $walletTransactionId = null,
    ): PaymentAllocation {
        $this->assertAllocationAllowed($order, $payment, $amountMinor, $currency);

        $allocation = new PaymentAllocation;
        $allocation->order_id = $order->id;
        $allocation->payment_id = $payment->id;
        $allocation->wallet_transaction_id = $walletTransactionId;
        $allocation->source_type = $sourceType;
        $allocation->amount_minor = $amountMinor;
        $allocation->currency = $currency;
        $allocation->status = PaymentStatus::Paid;
        $allocation->save();

        $this->syncPaidMinor($order);

        return $allocation;
    }

    public function refund(
        Payment $payment,
        ?Order $order,
        int $amountMinor,
        string $currency,
        string $reason,
        ?string $idempotencyKey = null,
        ?string $providerId = null,
    ): PaymentRefund {
        $this->assertRefundAllowed($payment, $order, $amountMinor, $currency);

        $existing = $idempotencyKey === null
            ? null
            : PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing === null) {
            $existing = new PaymentRefund;
            $existing->payment_id = $payment->id;
            $existing->provider_id = $providerId ?? $payment->provider_id;
            $existing->amount_minor = $amountMinor;
            $existing->currency = $currency;
            $existing->status = RefundStatus::Completed;
            $existing->reason = $reason;
            $existing->idempotency_key = $idempotencyKey;
            $existing->save();
        }

        $this->syncRefundedMinor($payment);

        if ($order !== null) {
            $this->syncOrderRefundStatus($order);
        }

        return $existing;
    }

    public function syncPaidFromAllocations(Order $order): void
    {
        $this->syncPaidMinor($order);
    }

    public function netPaid(Order $order): int
    {
        return $order->paid_minor - $this->completedRefundsForOrder($order);
    }

    private function syncPaidMinor(Order $order): void
    {
        $paid = $this->successfulAllocations($order);

        if ($paid < 0 || $paid > $order->total_minor) {
            throw new SettlementInvariantException('Allocated amount must stay between zero and the order total.');
        }

        $order->paid_minor = $paid;
        $next = match (true) {
            $paid === 0 => OrderPaymentStatus::Pending,
            $paid === $order->total_minor => OrderPaymentStatus::Paid,
            default => OrderPaymentStatus::PartiallyPaid,
        };
        $this->moveOrderPaymentStatus($order, $next);
        $order->save();
    }

    private function syncRefundedMinor(Payment $payment): void
    {
        $refunded = $this->completedRefunds($payment);

        if ($refunded < 0 || $refunded > $payment->amount_minor) {
            throw new SettlementInvariantException('Refunded amount must stay between zero and the payment amount.');
        }

        $payment->refunded_minor = $refunded;

        if ($refunded > 0) {
            $payment->status = $refunded >= $payment->amount_minor
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded;
        }

        $payment->save();
    }

    private function syncOrderRefundStatus(Order $order): void
    {
        $refunded = $this->completedRefundsForOrder($order);

        if ($refunded <= 0) {
            return;
        }

        $next = $order->paid_minor > 0 && $refunded >= $order->paid_minor
            ? OrderPaymentStatus::Refunded
            : OrderPaymentStatus::PartiallyRefunded;

        $this->moveOrderPaymentStatus($order, $next);
        $order->save();
    }

    private function moveOrderPaymentStatus(Order $order, OrderPaymentStatus $next): void
    {
        if ($order->payment_status === $next) {
            return;
        }

        PaymentStateMachine::assert($order->payment_status, $next);
        $order->payment_status = $next;
    }

    private function successfulAllocations(Order $order): int
    {
        return (int) PaymentAllocation::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount_minor');
    }

    private function completedRefunds(Payment $payment): int
    {
        return (int) PaymentRefund::query()
            ->where('payment_id', $payment->id)
            ->where('status', RefundStatus::Completed->value)
            ->sum('amount_minor');
    }

    private function completedRefundsForOrder(Order $order): int
    {
        return (int) PaymentRefund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereIn('payment_id', Payment::query()->where('order_id', $order->id)->select('id'))
            ->sum('amount_minor');
    }

    private function assertAllocationAllowed(Order $order, Payment $payment, int $amountMinor, string $currency): void
    {
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Failed], true)) {
            throw new SettlementInvariantException('A cancelled or failed order cannot be allocated.');
        }

        if (! in_array($payment->status, [PaymentStatus::Authorized, PaymentStatus::Paid], true)) {
            throw new SettlementInvariantException('Only an authorized or paid payment can be allocated.');
        }

        if ($payment->order_id !== null && $payment->order_id !== $order->id) {
            throw new SettlementInvariantException('Payment does not belong to the order.');
        }

        if ($amountMinor < 1 || strtoupper($currency) !== $order->currency || strtoupper($currency) !== $payment->currency) {
            throw new SettlementInvariantException('Allocation amount or currency is invalid.');
        }
    }

    private function assertRefundAllowed(Payment $payment, ?Order $order, int $amountMinor, string $currency): void
    {
        if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            throw new SettlementInvariantException('Only a paid payment can be refunded.');
        }

        if ($order !== null && $payment->order_id !== null && $payment->order_id !== $order->id) {
            throw new SettlementInvariantException('Payment does not belong to the order.');
        }

        if ($amountMinor < 1 || strtoupper($currency) !== $payment->currency) {
            throw new SettlementInvariantException('Refund amount or currency is invalid.');
        }
    }
}
