<?php

declare(strict_types=1);

use App\Integrations\Esim\Drivers\EsimAccess\EsimAccessProvider;
use App\Integrations\Esim\Drivers\Fake\FakeProvider;
use App\Integrations\Esim\Drivers\Template\TemplateEsimProvider;
use App\Integrations\Esim\Support\EsimAccessWebhookVerifier;
use App\Integrations\Esim\Support\FakeWebhookVerifier;
use App\Integrations\Esim\Support\TemplateWebhookVerifier;

return [
    'logging' => [
        'level' => env('PROVIDER_LOG_LEVEL', 'basic'),
        'max_body_bytes' => 8192,
    ],
    'timeout' => (int) env('PROVIDER_TIMEOUT', 20),
    'reconcile_ttl' => (int) env('PROVIDER_RECONCILE_TTL', 300),
    'retain_ambiguous_holds' => (bool) env('PROVIDER_RETAIN_AMBIGUOUS_HOLDS', false),
    'hold_ttl_minutes' => (int) env('WALLET_HOLD_TTL_MINUTES', 30),
    'api_log_retention_days' => (int) env('PROVIDER_API_LOG_RETENTION_DAYS', 14),
    'api_log_error_retention_days' => (int) env('PROVIDER_API_LOG_ERROR_RETENTION_DAYS', 90),
    'esimaccess' => [
        'base_url' => env('ESIMACCESS_BASE_URL', 'https://api.esimaccess.com'),
        'access_code' => env('ESIMACCESS_ACCESS_CODE'),
        'secret_key' => env('ESIMACCESS_SECRET_KEY'),
    ],
    'drivers' => [
        'fake' => FakeProvider::class,
        'template' => TemplateEsimProvider::class,
        'esimaccess' => EsimAccessProvider::class,
    ],
    'webhook_max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 8),
    'webhook_verifiers' => [
        'fake' => FakeWebhookVerifier::class,
        'template' => TemplateWebhookVerifier::class,
        'esimaccess' => EsimAccessWebhookVerifier::class,
    ],
];
