<?php

declare(strict_types=1);

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\RegisterUser;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('registers a customer with a profile, wallet, role, and token', function (): void {
    $result = app(RegisterUser::class)([
        'name' => 'Sara Traveler',
        'email' => 'sara@example.com',
        'password' => 'password123',
        'preferred_currency' => 'LYD',
    ]);

    expect($result['token'])->not->toBeEmpty();
    expect($result['user']->email)->toBe('sara@example.com');
    expect($result['user']->status)->toBe(UserStatus::Active);
    expect($result['user']->profile?->display_name)->toBe('Sara Traveler');
    expect($result['user']->hasRole(RoleName::User->value))->toBeTrue();
    expect($result['user']->wallets()->where('currency', 'LYD')->first()?->available_balance_minor)->toBe(0);
});

it('logs in an active user with the correct password', function (): void {
    $registered = app(RegisterUser::class)([
        'name' => 'Login User',
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $result = app(LoginUser::class)('login@example.com', 'password123');

    expect($result['user']->id)->toBe($registered['user']->id);
    expect($result['token'])->not->toBeEmpty();
    expect($result['user']->refresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials', function (): void {
    User::factory()->create(['email' => 'login@example.com']);

    app(LoginUser::class)('login@example.com', 'wrong-password');
})->throws(ValidationException::class);
