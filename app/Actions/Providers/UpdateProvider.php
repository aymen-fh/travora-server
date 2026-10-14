<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\RecordStatus;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\Request;

final class UpdateProvider
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $actor, Provider $provider, array $data, ?Request $request = null): Provider
    {
        $old = $provider->only(['name', 'priority', 'status', 'metadata']);

        if (isset($data['name'])) {
            $provider->name = $data['name'];
        }

        if (isset($data['priority'])) {
            $provider->priority = (int) $data['priority'];
        }

        if (isset($data['status'])) {
            $provider->status = $data['status'] instanceof RecordStatus
                ? $data['status']
                : RecordStatus::from((string) $data['status']);
        }

        if (array_key_exists('metadata', $data)) {
            $provider->metadata = $data['metadata'];
        }

        $provider->save();

        ($this->audit)(
            'provider.updated',
            $actor,
            Provider::class,
            $provider->id,
            $old,
            $provider->only(['name', 'priority', 'status', 'metadata']),
            $request?->ip(),
            $request?->userAgent(),
        );

        return $provider;
    }
}
