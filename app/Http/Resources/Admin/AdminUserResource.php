<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('profile');

        return [
            'id' => $this->id,
            'name' => $this->profile?->display_name ?: (trim(($this->profile?->first_name ?? '').' '.($this->profile?->last_name ?? '')) ?: null),
            'first_name' => $this->profile?->first_name,
            'last_name' => $this->profile?->last_name,
            'display_name' => $this->profile?->display_name,
            'avatar_url' => $this->profile?->avatar_url,
            'timezone' => $this->profile?->timezone,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status?->value,
            'preferred_language' => $this->profile?->preferred_language,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()->all()),
            'wallets' => $this->when(
                $request->user()?->can(PermissionName::WalletsView->value) && $this->relationLoaded('wallets'),
                fn () => $this->wallets->map(fn ($wallet): array => [
                    'id' => $wallet->id,
                    'currency' => $wallet->currency,
                    'available_balance_minor' => $wallet->available_balance_minor,
                    'held_balance_minor' => $wallet->held_balance_minor,
                    'status' => $wallet->status?->value,
                ])->values()->all()
            ),
            'orders_count' => $this->whenCounted('orders'),
            'subscriptions_count' => $this->subscriptions_count ?? null,
        ];
    }
}
