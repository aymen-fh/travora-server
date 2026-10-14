<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PermissionName;
use App\Enums\RefundStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\UserStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WebhookEventStatus;
use App\Models\EsimProfile;
use App\Models\EsimRenewal;
use App\Models\EsimTopup;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentRefund;
use App\Models\Provider;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;

final class BuildAdminDashboard
{
    /**
     * Each slice is queried only when the actor holds the matching permission.
     * Super Admin passes every check through Gate::before.
     *
     * @return array<string, mixed>
     */
    public function __invoke(User $actor, ?string $range = '30d', ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->window($range, $from, $to);

        $payload = [
            'range' => [
                'from' => $start->toIso8601String(),
                'to' => $end->toIso8601String(),
            ],
        ];

        if ($actor->can(PermissionName::UsersView->value)) {
            $payload['users_total'] = User::query()->count();
            $payload['users_active'] = User::query()->where('status', UserStatus::Active)->count();
            $payload['users_new'] = User::query()->whereBetween('created_at', [$start, $end])->count();
        }

        if ($actor->can(PermissionName::OrdersView->value)) {
            $orders = Order::query()->whereBetween('created_at', [$start, $end]);
            $payload['orders_total'] = (clone $orders)->count();
            $payload['orders_by_status'] = Order::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count)
                ->all();
            $payload['orders_by_day'] = $this->ordersByDay($start, $end);

            if ($actor->can(PermissionName::OrdersManage->value)) {
                $payload['failed_orders'] = (clone $orders)->where('status', OrderStatus::Failed)->count();
                $payload['pending_fulfillments'] = Order::query()
                    ->whereIn('fulfillment_status', [FulfillmentStatus::Pending, FulfillmentStatus::Processing])
                    ->count();
            }
        }

        if ($actor->can(PermissionName::FinanceRevenueView->value) || $actor->can(PermissionName::FinanceDashboardView->value) || $actor->can(PermissionName::PaymentsView->value)) {
            $payload['revenue_minor'] = $this->netRevenue($start, $end);
            $payload['revenue_by_day'] = $this->revenueByDay($start, $end);
            $payload['payment_activity'] = $this->paymentActivity($start, $end);
        }

        if ($actor->can(PermissionName::WalletsView->value)) {
            $payload['wallet_available_minor'] = (int) Wallet::query()->sum('available_balance_minor');
            $payload['wallet_held_minor'] = (int) Wallet::query()->sum('held_balance_minor');
        }

        if ($actor->can(PermissionName::EsimsView->value)) {
            $payload['esim_sales'] = EsimProfile::query()->whereBetween('created_at', [$start, $end])->count();
            $payload['esim_activations'] = EsimProfile::query()
                ->whereIn('status', [EsimProfileStatus::Activated, EsimProfileStatus::Installed])
                ->count();
            $payload['esim_by_status'] = EsimProfile::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count)
                ->all();
            $payload['renewals_count'] = EsimRenewal::query()->whereBetween('created_at', [$start, $end])->count();
        }

        if ($actor->can(PermissionName::TopupsView->value)) {
            $payload['topups_count'] = EsimTopup::query()->whereBetween('created_at', [$start, $end])->count();
        }

        if ($actor->can(PermissionName::TicketsView->value)) {
            $payload['tickets_open'] = SupportTicket::query()
                ->whereNotIn('status', [SupportTicketStatus::Resolved, SupportTicketStatus::Closed])
                ->count();
        }

        if ($actor->can(PermissionName::ProvidersView->value)) {
            $payload['providers_by_health'] = Provider::query()
                ->selectRaw('health_status, count(*) as aggregate')
                ->groupBy('health_status')
                ->pluck('aggregate', 'health_status')
                ->map(fn ($count): int => (int) $count)
                ->all();
        }

        if ($actor->can(PermissionName::WebhooksView->value)) {
            $payload['failed_webhooks'] = WebhookEvent::query()
                ->where('status', WebhookEventStatus::Failed)
                ->count();
        }

        if ($actor->can(PermissionName::OrdersView->value) || $actor->can(PermissionName::WalletsView->value)) {
            $overdueHolds = WalletHold::query()
                ->where('status', WalletHoldStatus::Active)
                ->where('created_at', '<', now()->subMinutes(15));

            $payload['overdue_active_holds_count'] = (clone $overdueHolds)->count();
            $payload['overdue_active_holds_minor'] = (int) (clone $overdueHolds)->sum('amount_minor');

            $manualReviewOrders = Order::query()
                ->where('metadata->reconciliation_status', 'manual_review_required');
            $payload['manual_review_orders_count'] = (clone $manualReviewOrders)->count();

            $discrepancyOrders = Order::query()
                ->where(function ($query): void {
                    $query->where('metadata->reconciliation_status', 'financial_discrepancy_detected')
                        ->orWhereNotNull('metadata->unreconciled_provider_fulfillment');
                });
            $payload['financial_discrepancies_count'] = (clone $discrepancyOrders)->count();
            $payload['financial_discrepancies_cost_minor'] = (int) (clone $discrepancyOrders)->sum('total_minor');

            $oldestDiscrepancy = (clone $discrepancyOrders)->oldest('created_at')->first();
            $payload['oldest_unresolved_discrepancy_at'] = $oldestDiscrepancy?->created_at?->toIso8601String();
        }

        return $payload;
    }

    /**
     * @return list<array{date: string, revenue_minor: int}>
     */
    private function revenueByDay(Carbon $start, Carbon $end): array
    {
        $totals = $this->netRevenueByDay($start, $end, $this->orderDaySql());

        $series = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'revenue_minor' => $totals[$key] ?? 0,
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return list<array{date: string, orders: int}>
     */
    private function ordersByDay(Carbon $start, Carbon $end): array
    {
        $daySql = $this->daySql(Order::query()->getConnection()->getDriverName());
        $totals = [];
        $rows = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw($daySql.' as day, COUNT(*) as orders')
            ->groupByRaw($daySql)
            ->get();

        foreach ($rows as $row) {
            $totals[substr((string) $row->day, 0, 10)] = (int) $row->orders;
        }

        $series = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        while ($cursor->lte($last)) {
            $key = $cursor->toDateString();
            $series[] = ['date' => $key, 'orders' => $totals[$key] ?? 0];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array{topup: int, purchase: int, refund: int}
     */
    private function paymentActivity(Carbon $start, Carbon $end): array
    {
        $base = Payment::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded]);

        return [
            'topup' => (int) (clone $base)->where('type', PaymentType::WalletTopup)->sum('amount_minor'),
            'purchase' => (int) (clone $base)->where('type', PaymentType::Charge)->sum('amount_minor'),
            'refund' => (int) (clone $base)->where('type', PaymentType::Refund)->sum('amount_minor'),
        ];
    }

    private function netRevenue(Carbon $start, Carbon $end): int
    {
        $allocated = (int) PaymentAllocation::query()
            ->where('status', PaymentStatus::Paid->value)
            ->whereHas('order', fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->sum('amount_minor');

        $refunded = (int) PaymentRefund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereHas('payment.order', fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->sum('amount_minor');

        return $allocated - $refunded;
    }

    /**
     * @return array<string, int>
     */
    private function netRevenueByDay(Carbon $start, Carbon $end, string $daySql): array
    {
        $allocated = [];
        $rows = PaymentAllocation::query()
            ->join('orders', 'orders.id', '=', 'payment_allocations.order_id')
            ->where('payment_allocations.status', PaymentStatus::Paid->value)
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw($daySql.' as day, SUM(payment_allocations.amount_minor) as revenue_minor')
            ->groupByRaw($daySql)
            ->get();

        foreach ($rows as $row) {
            $allocated[substr((string) $row->day, 0, 10)] = (int) $row->revenue_minor;
        }

        $refundRows = PaymentRefund::query()
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payment_refunds.status', RefundStatus::Completed->value)
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw($daySql.' as day, SUM(payment_refunds.amount_minor) as refunded_minor')
            ->groupByRaw($daySql)
            ->get();

        foreach ($refundRows as $row) {
            $key = substr((string) $row->day, 0, 10);
            $allocated[$key] = ($allocated[$key] ?? 0) - (int) $row->refunded_minor;
        }

        return $allocated;
    }

    private function orderDaySql(): string
    {
        return match (Order::query()->getConnection()->getDriverName()) {
            'pgsql' => "to_char(orders.created_at, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', orders.created_at)",
            default => 'DATE(orders.created_at)',
        };
    }

    private function daySql(string $driver): string
    {
        return match ($driver) {
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            default => 'DATE(created_at)',
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(?string $range, ?string $from, ?string $to): array
    {
        $end = now()->endOfDay();
        $start = now()->subDays(30)->startOfDay();

        return match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            '7d' => [now()->subDays(7)->startOfDay(), $end],
            '30d' => [$start, $end],
            'custom' => [
                $from ? Carbon::parse($from)->startOfDay() : $start,
                $to ? Carbon::parse($to)->endOfDay() : $end,
            ],
            default => [$start, $end],
        };
    }
}
