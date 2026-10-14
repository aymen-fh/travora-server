<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Rbac\StaffAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

final class ChangeStaffStatus
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, User $staff, UserStatus $status, ?string $reason = null, ?Request $request = null): User
    {
        $this->access->assertCanTouch($actor, $staff);

        if (! in_array($status, [UserStatus::Active, UserStatus::Suspended], true)) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($status === UserStatus::Suspended && ! $actor->can('staff.suspend')) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($status === UserStatus::Active && ! $actor->can('staff.activate')) {
            throw new AuthorizationException('Forbidden.');
        }

        if ($actor->id === $staff->id && $status === UserStatus::Suspended) {
            throw new AuthorizationException('Forbidden.');
        }

        $this->access->assertLastSuperAdminRemains($staff, null, $status);

        $previous = $staff->status?->value;
        $staff->status = $status;
        $staff->save();

        if ($status === UserStatus::Suspended) {
            $staff->tokens()->delete();
        }

        ($this->audit)(
            $status === UserStatus::Suspended ? 'staff.suspended' : 'staff.activated',
            $actor,
            User::class,
            $staff->id,
            ['status' => $previous],
            ['status' => $status->value, 'reason' => $reason],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $staff->fresh(['profile', 'roles']) ?? $staff;
    }
}
