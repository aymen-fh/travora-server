<?php

declare(strict_types=1);

return [
    'rate_limits' => [
        'api' => (int) env('RATE_LIMIT_API', 60),
        'sensitive' => (int) env('RATE_LIMIT_SENSITIVE', 20),
    ],
    'audit_log_retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),
    'required_env' => [
        'APP_KEY',
        'APP_URL',
        'DB_CONNECTION',
        'DB_DATABASE',
        'QUEUE_CONNECTION',
        'CACHE_STORE',
    ],
];
