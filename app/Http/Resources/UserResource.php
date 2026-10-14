<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('profile');

        return [
            'id' => $this->id,
            'name' => $this->profile?->display_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status?->value,
            'preferred_language' => $this->profile?->preferred_language,
            'theme' => $this->profile?->ui_theme ?? 'system',
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()->all()),
            'permissions' => $this->whenLoaded('roles', fn () => $this->getAllPermissions()->pluck('name')->unique()->values()->all()),
            'direct_permissions' => $this->whenLoaded('roles', fn () => $this->getDirectPermissions()->pluck('name')->unique()->values()->all()),
        ];
    }
}
