<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\RespondsWithAdminPage;
use App\Http\Controllers\Controller;
use App\Models\EsimRenewal;
use App\Models\EsimTopup;
use App\Models\NetworkOperator;
use App\Models\Notification;
use App\Models\Promotion;
use App\Models\SimInventory;
use App\Models\SimProduct;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only admin lists for models that already exist but had no admin route.
 * Tier 0: search, filter, and pagination stay in the query. No writes.
 */
final class OperationalIndexController extends Controller
{
    use RespondsWithAdminPage;

    public function promotions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $page = Promotion::query()
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(fn (Promotion $row): array => [
            'id' => $row->id,
            'name' => $row->name,
            'code' => $row->code,
            'type' => $row->type,
            'value_minor' => $row->value_minor,
            'percentage' => $row->percentage === null ? null : (float) $row->percentage,
            'starts_at' => $row->starts_at?->toIso8601String(),
            'ends_at' => $row->ends_at?->toIso8601String(),
            'usage_limit' => $row->usage_limit,
            'usage_count' => $row->usage_count,
            'per_user_limit' => $row->per_user_limit,
            'is_active' => $row->is_active,
        ])->values()->all(), $page);
    }

    public function trips(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Trip::class);

        $page = Trip::query()
            ->with(['user.profile', 'destinationCountry:id,name'])
            ->withCount('items')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(fn (Trip $row): array => [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'name' => $row->name,
            'destination' => $row->destinationCountry?->name,
            'starts_at' => $row->start_at?->toIso8601String(),
            'ends_at' => $row->end_at?->toIso8601String(),
            'status' => $row->status?->value,
            'items_count' => $row->items_count,
            'user' => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->profile?->display_name,
                'email' => $row->user->email,
            ] : null,
        ])->values()->all(), $page);
    }

    public function topups(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EsimTopup::class);

        $page = EsimTopup::query()
            ->with(['user.profile', 'esimProfile:id,iccid', 'provider:id,name'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('provider_reference', 'like', '%'.$search.'%')
                        ->orWhereHas('esimProfile', fn ($profile) => $profile->where('iccid', 'like', '%'.$search.'%'))
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(fn (EsimTopup $row): array => [
            'id' => $row->id,
            'esim_profile_id' => $row->esim_profile_id,
            'order_id' => $row->order_id,
            'provider_id' => $row->provider_id,
            'provider_reference' => $row->provider_reference,
            'data_mb' => $row->data_mb,
            'is_unlimited' => $row->is_unlimited,
            'amount_minor' => $row->amount_minor,
            'currency' => $row->currency,
            'status' => $row->status?->value,
            'created_at' => $row->created_at?->toIso8601String(),
            'iccid' => $row->esimProfile?->iccid,
            'provider_name' => $row->provider?->name,
            'user' => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->profile?->display_name,
                'email' => $row->user->email,
            ] : null,
        ])->values()->all(), $page);
    }

    public function renewals(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EsimRenewal::class);

        $page = EsimRenewal::query()
            ->with(['user.profile', 'currentPlan:id,name', 'newPlan:id,name', 'esimProfile:id,iccid', 'provider:id,name'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('provider_reference', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(fn (EsimRenewal $row): array => [
            'id' => $row->id,
            'esim_profile_id' => $row->esim_profile_id,
            'current_plan_id' => $row->current_plan_id,
            'new_plan_id' => $row->new_plan_id,
            'order_id' => $row->order_id,
            'provider_id' => $row->provider_id,
            'provider_reference' => $row->provider_reference,
            'status' => $row->status?->value,
            'amount_minor' => $row->amount_minor,
            'currency' => $row->currency,
            'effective_at' => $row->effective_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
            'current_plan_name' => $row->currentPlan?->name,
            'new_plan_name' => $row->newPlan?->name,
            'iccid' => $row->esimProfile?->iccid,
            'provider_name' => $row->provider?->name,
            'user' => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->profile?->display_name,
                'email' => $row->user->email,
            ] : null,
        ])->values()->all(), $page);
    }

    public function simProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SimProduct::class);

        $page = SimProduct::query()
            ->with(['provider:id,name', 'country:id,name'])
            ->withCount('inventory')
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(fn (SimProduct $row): array => [
            'id' => $row->id,
            'provider_id' => $row->provider_id,
            'country_id' => $row->country_id,
            'type' => $row->type,
            'name' => $row->name,
            'data_mb' => $row->data_mb,
            'is_unlimited' => $row->is_unlimited,
            'validity_days' => $row->validity_days,
            'price_minor' => $row->price_minor,
            'currency' => $row->currency,
            'status' => $row->status?->value,
            'provider_name' => $row->provider?->name,
            'country_name' => $row->country?->name,
            'inventory_available' => $row->inventory_count,
        ])->values()->all(), $page);
    }

    public function simInventory(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SimInventory::class);

        $page = SimInventory::query()
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('iccid', 'like', '%'.$search.'%')
                        ->orWhere('msisdn', 'like', '%'.$search.'%')
                        ->orWhere('imsi', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(fn (SimInventory $row): array => [
            'id' => $row->id,
            'sim_product_id' => $row->sim_product_id,
            'external_inventory_id' => $row->external_inventory_id,
            'iccid' => $row->iccid,
            'imsi' => $row->imsi,
            'msisdn' => $row->msisdn,
            'status' => $row->status,
            'activated_at' => $row->activated_at?->toIso8601String(),
            'expires_at' => $row->expires_at?->toIso8601String(),
        ])->values()->all(), $page);
    }

    public function networks(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NetworkOperator::class);

        $page = NetworkOperator::query()
            ->with(['country:id,name', 'technologies'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('mcc', 'like', '%'.$search.'%')
                        ->orWhere('mnc', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(fn (NetworkOperator $row): array => [
            'id' => $row->id,
            'country_id' => $row->country_id,
            'country_name' => $row->country?->name,
            'name' => $row->name,
            'mcc' => $row->mcc,
            'mnc' => $row->mnc,
            'is_active' => $row->is_active,
            'technologies' => $row->technologies->pluck('technology')->values()->all(),
        ])->values()->all(), $page);
    }

    public function notifications(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notification::class);

        $page = Notification::query()
            ->with('user.profile')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$search.'%'));
                });
            })
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage($page->getCollection()->map(function (Notification $row): array {
            $channel = is_array($row->data) ? ($row->data['channel'] ?? null) : null;

            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'type' => $row->type,
                'title' => $row->title,
                'channel' => is_string($channel) ? $channel : 'in_app',
                'status' => $row->read_at ? 'read' : ($row->delivered_at ? 'delivered' : 'pending'),
                'created_at' => $row->created_at?->toIso8601String(),
                'read_at' => $row->read_at?->toIso8601String(),
                'user' => $row->user ? [
                    'id' => $row->user->id,
                    'name' => $row->user->profile?->display_name,
                    'email' => $row->user->email,
                ] : null,
            ];
        })->values()->all(), $page);
    }
}
