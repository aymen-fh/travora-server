<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Support;

final class SecretMasker
{
    /**
     * @var list<string>
     */
    private const KEYS = [
        'api_key',
        'api_secret',
        'password',
        'secret',
        'token',
        'authorization',
        'webhook_secret',
        'access_token',
        'refresh_token',
        'card_number',
        'cvv',
        'otp',
        'activation',
        'confirmation_code',
        'document_number',
        'identity',
        'pan',
        'pin',
        'private_key',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mask(array $payload): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);

            if (self::isSensitive($normalized)) {
                $masked[$key] = '***';

                continue;
            }

            $masked[$key] = is_array($value) ? self::mask($value) : $value;
        }

        return $masked;
    }

    public static function maskValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = strlen($value);

        if ($length <= 4) {
            return '***';
        }

        return str_repeat('*', $length - 4).substr($value, -4);
    }

    private static function isSensitive(string $key): bool
    {
        foreach (self::KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
