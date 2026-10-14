<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimRenewal;
use App\Models\EsimTopup;
use App\Models\NetworkOperator;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Provider;
use App\Models\SimInventory;
use App\Models\SimProduct;
use App\Models\SupportTicket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $like = User::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $needle = '%'.$query.'%';
        if (mb_strlen($query) < 2) {
            return ApiResponse::success([]);
        }

        $limit = min(5, max(1, $request->integer('per_type', 4)));
        $user = $request->user();
        $results = [];

        if ($user->can(PermissionName::UsersView->value)) {
            $results = array_merge($results, $this->rows(
                User::query()->with('profile')->where(function (Builder $inner) use ($like, $needle): void {
                    $inner->where('email', $like, $needle)
                        ->orWhere('phone', $like, $needle)
                        ->orWhereHas('profile', function (Builder $profile) use ($like, $needle): void {
                            $profile->where('display_name', $like, $needle)
                                ->orWhere('first_name', $like, $needle)
                                ->orWhere('last_name', $like, $needle);
                        });
                }),
                $limit,
                'user',
                fn (User $item): array => [
                    'id' => $item->id,
                    'title' => $item->profile?->display_name ?: $item->email,
                    'subtitle' => $item->email,
                    'status' => $item->status?->value,
                    'href' => '/users/'.$item->id,
                ],
            ));
        }

        if ($user->can(PermissionName::OrdersView->value)) {
            $results = array_merge($results, $this->rows(
                Order::query()->where(function (Builder $inner) use ($like, $needle): void {
                    $inner->where('order_number', $like, $needle)
                        ->orWhere('customer_email_snapshot', $like, $needle);
                }),
                $limit,
                'order',
                fn (Order $item): array => [
                    'id' => $item->id,
                    'title' => $item->order_number,
                    'subtitle' => $item->currency,
                    'status' => $item->status?->value,
                    'href' => '/orders/'.$item->id,
                ],
            ));
            $results = array_merge($results, $this->rows(
                Trip::query()->where('name', $like, $needle),
                $limit,
                'trip',
                fn (Trip $item): array => [
                    'id' => $item->id,
                    'title' => (string) $item->name,
                    'subtitle' => null,
                    'status' => $item->status?->value ?? (string) $item->status,
                    'href' => '/trips',
                ],
            ));
        }

        if ($user->can(PermissionName::EsimProfilesView->value)) {
            $results = array_merge($results, $this->rows(
                EsimProfile::query()->where('iccid', $like, $needle),
                $limit,
                'esim',
                fn (EsimProfile $item): array => [
                    'id' => $item->id,
                    'title' => (string) $item->iccid,
                    'subtitle' => null,
                    'status' => $item->status?->value,
                    'href' => '/esims/'.$item->id,
                ],
            ));
            $results = array_merge($results, $this->rows(
                EsimTopup::query()->where('provider_reference', $like, $needle),
                $limit,
                'topup',
                fn (EsimTopup $item): array => [
                    'id' => $item->id,
                    'title' => $item->provider_reference ?: $item->id,
                    'subtitle' => null,
                    'status' => $item->status?->value ?? (string) $item->status,
                    'href' => '/topups',
                ],
            ));
            $results = array_merge($results, $this->rows(
                EsimRenewal::query()->where('provider_reference', $like, $needle),
                $limit,
                'renewal',
                fn (EsimRenewal $item): array => [
                    'id' => $item->id,
                    'title' => $item->provider_reference ?: $item->id,
                    'subtitle' => null,
                    'status' => $item->status?->value ?? (string) $item->status,
                    'href' => '/renewals',
                ],
            ));
        }

        if ($user->can(PermissionName::PaymentsView->value)) {
            $results = array_merge($results, $this->rows(
                Payment::query()->where('payment_number', $like, $needle),
                $limit,
                'payment',
                fn (Payment $item): array => [
                    'id' => $item->id,
                    'title' => $item->payment_number,
                    'subtitle' => $item->currency,
                    'status' => $item->status?->value,
                    'href' => '/payments/'.$item->id,
                ],
            ));
        }

        if ($user->can(PermissionName::SupportTicketsView->value)) {
            $results = array_merge($results, $this->rows(
                SupportTicket::query()->where('subject', $like, $needle),
                $limit,
                'ticket',
                fn (SupportTicket $item): array => [
                    'id' => $item->id,
                    'title' => (string) $item->subject,
                    'subtitle' => null,
                    'status' => $item->status?->value,
                    'href' => '/support/'.$item->id,
                ],
            ));
        }

        if ($user->can(PermissionName::ProvidersView->value)) {
            $results = array_merge($results, $this->rows(
                Provider::query()->where('name', $like, $needle),
                $limit,
                'provider',
                fn (Provider $item): array => [
                    'id' => $item->id,
                    'title' => $item->name,
                    'subtitle' => $item->code,
                    'status' => $item->status?->value,
                    'href' => '/providers/'.$item->id,
                ],
            ));
        }

        if ($user->can(PermissionName::EsimPlansView->value)) {
            $results = array_merge($results, $this->rows(
                EsimPlan::query()->where('name', $like, $needle),
                $limit,
                'esim_plan',
                fn (EsimPlan $item): array => [
                    'id' => $item->id,
                    'title' => $item->name,
                    'subtitle' => null,
                    'status' => $item->status?->value ?? (string) $item->status,
                    'href' => '/catalog/esim-plans/'.$item->id,
                ],
            ));
            $results = array_merge($results, $this->rows(
                NetworkOperator::query()->where('name', $like, $needle),
                $limit,
                'network',
                fn (NetworkOperator $item): array => [
                    'id' => $item->id,
                    'title' => $item->name,
                    'subtitle' => null,
                    'status' => null,
                    'href' => '/geography/networks',
                ],
            ));
        }

        if ($user->can(PermissionName::ProductsView->value)) {
            $results = array_merge($results, $this->rows(
                Promotion::query()->where(function (Builder $inner) use ($like, $needle): void {
                    $inner->where('name', $like, $needle)->orWhere('code', $like, $needle);
                }),
                $limit,
                'promotion',
                fn (Promotion $item): array => [
                    'id' => $item->id,
                    'title' => $item->name,
                    'subtitle' => $item->code,
                    'status' => $item->is_active ? 'active' : 'inactive',
                    'href' => '/promotions',
                ],
            ));
            $results = array_merge($results, $this->rows(
                SimProduct::query()->where('name', $like, $needle),
                $limit,
                'sim_product',
                fn (SimProduct $item): array => [
                    'id' => $item->id,
                    'title' => $item->name,
                    'subtitle' => null,
                    'status' => $item->status?->value ?? (string) $item->status,
                    'href' => '/catalog/sim',
                ],
            ));
            $results = array_merge($results, $this->rows(
                SimInventory::query()->where('iccid', $like, $needle),
                $limit,
                'sim_inventory',
                fn (SimInventory $item): array => [
                    'id' => $item->id,
                    'title' => (string) $item->iccid,
                    'subtitle' => null,
                    'status' => $item->status?->value ?? (string) $item->status,
                    'href' => '/catalog/sim-inventory',
                ],
            ));
        }

        if ($user->can(PermissionName::DashboardView->value) || $user->can(PermissionName::SupportTicketsView->value)) {
            $results = array_merge($results, $this->rows(
                Notification::query()->where('title', $like, $needle),
                $limit,
                'notification',
                fn (Notification $item): array => [
                    'id' => $item->id,
                    'title' => (string) $item->title,
                    'subtitle' => null,
                    'status' => $item->read_at ? 'read' : 'pending',
                    'href' => '/notifications',
                ],
            ));
        }

        return ApiResponse::success(array_values($results));
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  callable(mixed): array{id: string, title: string, subtitle: ?string, status: ?string, href: string}  $map
     * @return list<array<string, mixed>>
     */
    private function rows(Builder $query, int $limit, string $type, callable $map): array
    {
        return $query->limit($limit)->get()->map(function (mixed $item) use ($type, $map): array {
            return ['type' => $type, ...$map($item)];
        })->all();
    }
}
