<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class VerifyPasswordResetOtp
{
    public function __invoke(string $email, string $code): void
    {
        $otp = OtpVerification::query()
            ->where('target', $email)
            ->where('purpose', 'password_reset')
            ->latest('created_at')
            ->first();

        if ($otp === null) {
            throw ValidationException::withMessages(['code' => 'The code is invalid.']);
        }

        if ($otp->consumed_at !== null || $otp->verified_at !== null) {
            throw ValidationException::withMessages(['code' => 'The code was already used.']);
        }

        if ($otp->expires_at !== null && $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'The code has expired.']);
        }

        if ($otp->attempts >= 5) {
            throw new TooManyRequestsHttpException(60, 'Too many attempts.');
        }

        if (! Hash::check($code, (string) $otp->code_hash)) {
            $otp->attempts++;
            $otp->save();
            throw ValidationException::withMessages(['code' => 'The code is invalid.']);
        }

        $otp->verified_at = now();
        $otp->consumed_at = now();
        $otp->save();
    }
}
