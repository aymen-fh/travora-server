<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\HealthStatus;
use App\Enums\ProviderType;
use App\Enums\RecordStatus;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\Request;

final class CreateProvider
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array{name: string, code: string, type?: string, status?: string, priority?: int, metadata?: array<string, mixed>|null}  $data
     */
    public function __invoke(User $actor, array $data, ?Request $request = null): Provider
    {
        $provider = new Provider;
        $provider->name = $data['name'];
        $provider->code = $data['code'];
        $provider->type = ProviderType::from($data['type'] ?? ProviderType::Esim->value);
        $provider->status = RecordStatus::from($data['status'] ?? RecordStatus::Active->value);
        $provider->priority = (int) ($data['priority'] ?? 100);
        $provider->health_status = HealthStatus::Unknown;
        $provider->metadata = $data['metadata'] ?? null;
        $provider->save();

        ($this->audit)(
            'provider.created',
            $actor,
            Provider::class,
            $provider->id,
            null,
            [
                'name' => $provider->name,
                'code' => $provider->code,
                'type' => $provider->type->value,
            ],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $provider;
    }
}
