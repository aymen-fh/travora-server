<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\TokenAbilities;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    /**
     * @return array{user: User, token: string}
     */
    public function __invoke(string $email, string $password, string $deviceName = 'mobile'): array
    {
        $key = 'login:'.sha1(strtolower($email));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Try again later.'),
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => __('Invalid credentials.'),
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => __('Account is not active.'),
            ]);
        }

        RateLimiter::clear($key);

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken($deviceName, TokenAbilities::for($user))->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
