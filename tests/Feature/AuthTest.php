<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('registers a customer with a profile, LYD wallet, and token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Sara Traveler',
        'email' => 'sara@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'sara@example.com')
        ->assertJsonPath('data.user.name', 'Sara Traveler')
        ->assertJsonPath('data.user.id', User::query()->where('email', 'sara@example.com')->value('id'))
        ->assertJsonMissingPath('data.user.uuid')
        ->assertJsonMissingPath('data.user.password_hash')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['token', 'user' => ['id', 'email', 'name']],
            'meta',
        ]);

    $user = User::query()->where('email', 'sara@example.com')->firstOrFail();

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'display_name' => 'Sara Traveler',
    ]);
    $this->assertDatabaseHas('wallets', [
        'user_id' => $user->id,
        'currency' => 'LYD',
        'available_balance_minor' => 0,
    ]);
    $this->assertDatabaseMissing('wallets', ['currency' => 'USD']);
});

it('rejects invalid registration payloads with a 422 envelope', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Sara',
        'email' => 'not-an-email',
        'password' => 'short',
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['success', 'message', 'errors', 'code']);
});

it('logs in and returns the current user without secrets', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Login User',
        'email' => 'login@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $login->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'login@example.com')
        ->assertJsonPath('data.user.name', 'Login User')
        ->assertJsonMissingPath('data.user.uuid')
        ->assertJsonMissingPath('data.user.password_hash');

    $token = $login->json('data.token');

    $this->getJson('/api/v1/auth/me', [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'login@example.com')
        ->assertJsonPath('data.name', 'Login User')
        ->assertJsonMissingPath('data.uuid')
        ->assertJsonMissingPath('data.password_hash')
        ->assertJsonStructure(['success', 'message', 'data', 'meta']);
});

it('rejects guests from protected routes with a 401 envelope', function (): void {
    $this->getJson('/api/v1/orders')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'UNAUTHENTICATED')
        ->assertJsonStructure(['success', 'message', 'errors', 'code']);
});

it('forbids customers from admin routes with a 403 envelope', function (): void {
    Sanctum::actingAs(customer());

    $this->getJson('/api/v1/admin/dashboard')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonStructure(['success', 'message', 'errors', 'code']);
});
