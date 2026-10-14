<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class SettingsRepository
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember('settings.'.$key, 300, function () use ($key): ?string {
            return Setting::query()->where('key', $key)->value('value');
        });

        if ($value === null) {
            return $default ?? config('travora.'.$key);
        }

        $type = Setting::query()->where('key', $key)->value('type') ?? 'string';

        return match ($type) {
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'int', 'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );

        Cache::forget('settings.'.$key);
    }

    public function forgetCatalogCache(): void
    {
        Cache::forget('catalog.countries');
        Cache::forget('catalog.regions');
        Cache::forget('catalog.products');
    }
}
