<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\Locale;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Support\Auth\TokenAbilities;
use Illuminate\Support\Facades\DB;

final class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null, preferred_language?: string, preferred_currency?: string, device_name?: string}  $data
     * @return array{user: User, token: string}
     */
    public function __invoke(array $data): array
    {
        $currency = strtoupper($data['preferred_currency'] ?? 'LYD');
        $language = Locale::tryFrom($data['preferred_language'] ?? 'en') ?? Locale::English;

        return DB::transaction(function () use ($data, $currency, $language): array {
            $user = new User;
            $user->email = $data['email'];
            $user->phone = $data['phone'] ?? null;
            $user->password_hash = $data['password'];
            $user->status = UserStatus::Active;
            $user->save();

            $profile = new UserProfile;
            $profile->user_id = $user->id;
            $profile->display_name = $data['name'];
            $profile->preferred_language = $language->value;
            $profile->save();

            $user->assignRole(RoleName::User->value);

            $wallet = new Wallet;
            $wallet->user_id = $user->id;
            $wallet->currency = $currency;
            $wallet->available_balance_minor = 0;
            $wallet->held_balance_minor = 0;
            $wallet->status = WalletStatus::Active;
            $wallet->save();

            $token = $user->createToken($data['device_name'] ?? 'mobile', [TokenAbilities::Mobile])->plainTextToken;

            return ['user' => $user->load('profile'), 'token' => $token];
        });
    }
}
