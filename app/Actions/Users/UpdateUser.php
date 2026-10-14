<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Locale;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;

final class UpdateUser
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array{phone?: string|null, display_name?: string, preferred_language?: string}  $data
     */
    public function __invoke(User $actor, User $user, array $data, ?Request $request = null): User
    {
        $user->loadMissing('profile');

        $old = [
            'phone' => $user->phone,
            'display_name' => $user->profile?->display_name,
            'preferred_language' => $user->profile?->preferred_language,
        ];

        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
            $user->save();
        }

        $profile = $user->profile ?? new UserProfile;
        $profile->user_id = $user->id;

        if (isset($data['display_name'])) {
            $profile->display_name = $data['display_name'];
        }

        if (isset($data['preferred_language'])) {
            $profile->preferred_language = Locale::tryFrom($data['preferred_language'])?->value
                ?? $profile->preferred_language;
        }

        $profile->save();

        ($this->audit)(
            'user.updated',
            $actor,
            User::class,
            $user->id,
            $old,
            $data,
            $request?->ip(),
            $request?->userAgent(),
        );

        return $user->fresh(['profile', 'roles']) ?? $user;
    }
}
