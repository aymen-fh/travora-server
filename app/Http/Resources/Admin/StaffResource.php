<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\Rbac\RolePermissionMatrix;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['profile', 'roles']);
        $role = $this->roles->pluck('name')->first(
            fn (string $name): bool => in_array($name, RolePermissionMatrix::staffRoleNames(), true),
        );

        $payload = [
            'id' => $this->id,
            'name' => $this->profile?->display_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status?->value,
            'role' => $role,
            'role_label' => $role ? RolePermissionMatrix::label($role) : null,
            'roles' => $this->roles->pluck('name')->values()->all(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($request->routeIs('admin.staff.show', 'admin.staff.permissions')) {
            $payload = array_merge($payload, RolePermissionMatrix::breakdown($this->resource));
            $payload['role_permissions_count'] = count($payload['role_permissions']);
            $payload['direct_permissions_count'] = count($payload['direct_permissions']);
            $payload['effective_permissions_count'] = count($payload['effective_permissions']);
        }

        return $payload;
    }
}
