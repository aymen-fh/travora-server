<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\OtpVerification;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Hash;

final class IssuePasswordResetOtp
{
    public function __invoke(string $email): void
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return;
        }

        OtpVerification::query()
            ->where('target', $email)
            ->where('purpose', 'password_reset')
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = new OtpVerification;
        $otp->user_id = $user->id;
        $otp->channel = 'email';
        $otp->target = $email;
        $otp->purpose = 'password_reset';
        $otp->code_hash = Hash::make($code);
        $otp->expires_at = now()->addMinutes(10);
        $otp->attempts = 0;
        $otp->save();

        $user->notify(new PasswordResetCodeNotification($code));
    }
}
