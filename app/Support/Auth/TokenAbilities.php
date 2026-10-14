<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Enums\RoleName;
use App\Models\User;

final class TokenAbilities
{
    public const Mobile = 'mobile';

    public const Admin = 'admin';

    /**
     * @return list<string>
     */
    public static function for(User $user): array
    {
        $staff = array_values(array_filter(
            array_map(fn (RoleName $role): string => $role->value, RoleName::cases()),
            fn (string $role): bool => $role !== RoleName::User->value,
        ));

        if ($user->hasAnyRole($staff)) {
            return [self::Admin, self::Mobile];
        }

        return [self::Mobile];
    }
}
