<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Auth\IssuePasswordResetOtp;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Rbac\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CreateStaff
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly RecordAuditLog $audit,
        private readonly IssuePasswordResetOtp $reset,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, role: string, status?: string}  $data
     */
    public function __invoke(User $actor, array $data, ?Request $request = null): User
    {
        $role = $data['role'];
        $this->access->assertCanAssignRole($actor, $role);

        $status = UserStatus::from($data['status'] ?? UserStatus::Active->value);
        if (! in_array($status, [UserStatus::Active, UserStatus::Suspended], true)) {
            $status = UserStatus::Active;
        }

        $user = new User;
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->password_hash = Str::password(40);
        $user->status = $status;
        $user->email_verified_at = now();
        $user->save();

        $name = trim($data['name']);
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $profile = new UserProfile;
        $profile->user_id = $user->id;
        $profile->first_name = $parts[0] ?? $name;
        $profile->last_name = $parts[1] ?? null;
        $profile->display_name = $name;
        $profile->preferred_language = 'en';
        $profile->save();

        $user->syncRoles([$role]);

        if ($status === UserStatus::Active) {
            ($this->reset)($user->email);
        }

        ($this->audit)(
            'staff.created',
            $actor,
            User::class,
            $user->id,
            null,
            [
                'email' => $user->email,
                'role' => $role,
                'status' => $status->value,
            ],
            $request?->ip(),
            $request?->userAgent(),
        );

        ($this->audit)(
            'staff.role_changed',
            $actor,
            User::class,
            $user->id,
            ['role' => null],
            ['role' => $role],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $user->fresh(['profile', 'roles']) ?? $user;
    }
}
