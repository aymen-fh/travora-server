<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;

final class SuspendUser
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, User $user, string $reason, ?Request $request = null): User
    {
        $old = ['status' => $user->status?->value];
        $user->status = UserStatus::Suspended;
        $user->save();

        ($this->audit)(
            'user.suspended',
            $actor,
            User::class,
            $user->id,
            $old,
            ['status' => UserStatus::Suspended->value, 'reason' => $reason],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $user->fresh(['profile', 'roles']) ?? $user;
    }
}
