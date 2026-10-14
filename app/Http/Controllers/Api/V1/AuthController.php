<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Auth\IssuePasswordResetOtp;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\VerifyPasswordResetOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $result = $register($request->validated());

        return ApiResponse::created([
            'user' => (new UserResource($result['user']->load(['roles', 'profile'])))->resolve(),
            'token' => $result['token'],
        ], 'Registered');
    }

    public function login(LoginRequest $request, LoginUser $login): JsonResponse
    {
        $result = $login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'mobile')->toString(),
        );

        return ApiResponse::success([
            'user' => (new UserResource($result['user']->load(['roles', 'profile'])))->resolve(),
            'token' => $result['token'],
        ], 'Authenticated');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()->load(['roles', 'profile'])));
    }

    public function forgotPassword(Request $request, IssuePasswordResetOtp $issue): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $issue($request->string('email')->toString());

        return ApiResponse::success(null, 'If the email exists, a reset code was sent.');
    }

    public function verifyOtp(Request $request, VerifyPasswordResetOtp $verify): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $verify($data['email'], $data['code']);

        return ApiResponse::success(['verified' => true], 'Code confirmed');
    }

    public function preferences(Request $request): JsonResponse
    {
        $profile = $request->user()->profile()->first();

        return ApiResponse::success([
            'preferred_language' => $profile?->preferred_language ?? 'en',
            'theme' => $profile?->ui_theme ?? 'system',
        ]);
    }

    public function updatePreferences(Request $request, RecordAuditLog $audit): JsonResponse
    {
        $data = $request->validate([
            'preferred_language' => ['sometimes', 'in:en,ar'],
            'theme' => ['sometimes', 'in:light,dark,system'],
        ]);

        $user = $request->user();
        $profile = $user->profile()->first();
        if ($profile === null) {
            $profile = new UserProfile;
            $profile->user_id = $user->id;
            $profile->save();
        }
        $previous = [
            'preferred_language' => $profile->preferred_language,
            'theme' => $profile->ui_theme,
        ];
        if (array_key_exists('preferred_language', $data)) {
            $profile->preferred_language = $data['preferred_language'];
        }
        if (array_key_exists('theme', $data)) {
            $profile->ui_theme = $data['theme'];
        }
        $profile->save();

        $audit(
            'user.preferences.updated',
            $user,
            User::class,
            $user->id,
            $previous,
            ['preferred_language' => $profile->preferred_language, 'theme' => $profile->ui_theme],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success([
            'preferred_language' => $profile->preferred_language,
            'theme' => $profile->ui_theme ?? 'system',
        ], 'Preferences saved');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password): void {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return ApiResponse::success(null, 'Password reset');
    }

    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->orderByDesc('last_used_at')->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success($tokens);
    }

    public function revokeSession(Request $request, int $token, RecordAuditLog $audit): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->whereKey($token)->delete();

        $audit(
            'user.session.revoked',
            $user,
            User::class,
            $user->id,
            null,
            ['token_id' => $token],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(null, 'Session revoked');
    }
}
