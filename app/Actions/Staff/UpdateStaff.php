<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Rbac\StaffAccess;
use Illuminate\Http\Request;

final class UpdateStaff
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array{name?: string, email?: string, phone?: string|null, role?: string}  $data
     */
    public function __invoke(User $actor, User $staff, array $data, ?Request $request = null): User
    {
        $this->access->assertCanTouch($actor, $staff);

        $previousRole = $staff->roles->pluck('name')->first(fn (string $name): bool => $name !== 'user');
        $previous = [
            'name' => $staff->profile?->display_name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'role' => $previousRole,
        ];

        if (array_key_exists('role', $data) && $data['role'] !== $previousRole) {
            if (! $actor->can('staff.roles.manage')) {
                abort(403);
            }
            $this->access->assertCanAssignRole($actor, $data['role']);
            $this->access->assertLastSuperAdminRemains($staff, $data['role']);
        }

        if (array_key_exists('email', $data)) {
            $staff->email = $data['email'];
        }
        if (array_key_exists('phone', $data)) {
            $staff->phone = $data['phone'];
        }
        $staff->save();

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            $parts = preg_split('/\s+/', $name, 2) ?: [$name];
            $profile = $staff->profile ?? new UserProfile;
            $profile->user_id = $staff->id;
            $profile->first_name = $parts[0] ?? $name;
            $profile->last_name = $parts[1] ?? null;
            $profile->display_name = $name;
            $profile->save();
        }

        if (array_key_exists('role', $data) && $data['role'] !== $previousRole) {
            $staff->syncRoles([$data['role']]);
            ($this->audit)(
                'staff.role_changed',
                $actor,
                User::class,
                $staff->id,
                ['role' => $previousRole],
                ['role' => $data['role']],
                $request?->ip(),
                $request?->userAgent(),
            );
        }

        $fresh = $staff->fresh(['profile', 'roles']) ?? $staff;
        $nextRole = $fresh->roles->pluck('name')->first(fn (string $name): bool => $name !== 'user');

        ($this->audit)(
            'staff.updated',
            $actor,
            User::class,
            $staff->id,
            $previous,
            [
                'name' => $fresh->profile?->display_name,
                'email' => $fresh->email,
                'phone' => $fresh->phone,
                'role' => $nextRole,
            ],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $fresh;
    }
}
