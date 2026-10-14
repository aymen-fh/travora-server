<?php

declare(strict_types=1);

use App\Jobs\CleanupAuditLogsJob;
use App\Models\AuditLog;
use App\Models\User;

it('accepts the phpunit environment without production strictness', function (): void {
    $this->artisan('travora:validate-env')
        ->assertSuccessful()
        ->expectsOutput('Environment configuration is valid.');
});

it('rejects the testing stack under production strict rules', function (): void {
    $this->artisan('travora:validate-env', ['--strict' => true])->assertFailed();
});

it('reports health without leaking secrets', function (): void {
    $this->get('/up')->assertOk();

    $this->artisan('travora:health')
        ->assertSuccessful()
        ->expectsOutputToContain('ok  app')
        ->expectsOutputToContain('ok  database')
        ->expectsOutputToContain('ok  cache')
        ->expectsOutputToContain('ok  queue')
        ->doesntExpectOutputToContain('secret')
        ->doesntExpectOutputToContain('APP_KEY')
        ->doesntExpectOutputToContain('password_hash');
});

it('deletes audit logs older than the retention window', function (): void {
    config(['travora.audit_log_retention_days' => 30]);

    $actor = User::factory()->create();
    $old = new AuditLog;
    $old->user_id = $actor->id;
    $old->action = 'user.suspended';
    $old->created_at = now()->subDays(40);
    $old->save();

    $fresh = new AuditLog;
    $fresh->user_id = $actor->id;
    $fresh->action = 'wallet.adjusted';
    $fresh->created_at = now();
    $fresh->save();

    (new CleanupAuditLogsJob)->handle();

    expect(AuditLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->first()?->id)->toBe($fresh->id);
});

it('keeps .env.example free of live secrets', function (): void {
    $example = (string) file_get_contents(base_path('.env.example'));

    expect($example)->toContain('APP_KEY=')
        ->and($example)->not->toContain('base64:2fl+')
        ->and($example)->not->toContain('vault://')
        ->and($example)->toContain('AUDIT_LOG_RETENTION_DAYS')
        ->and($example)->toContain('RATE_LIMIT_SENSITIVE');
});
