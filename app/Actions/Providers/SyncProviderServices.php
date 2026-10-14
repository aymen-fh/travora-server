<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Provider;
use App\Models\ProviderService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SyncProviderServices
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  list<array{service_type: string, is_enabled?: bool, priority?: int}>  $services
     */
    public function __invoke(User $actor, Provider $provider, array $services, ?Request $request = null): Provider
    {
        DB::transaction(function () use ($provider, $services): void {
            $types = [];

            foreach ($services as $row) {
                $type = (string) $row['service_type'];
                $types[] = $type;

                $service = ProviderService::query()
                    ->where('provider_id', $provider->id)
                    ->where('service_type', $type)
                    ->first();

                if ($service === null) {
                    $service = new ProviderService;
                    $service->provider_id = $provider->id;
                    $service->service_type = $type;
                }

                $service->is_enabled = (bool) ($row['is_enabled'] ?? true);
                $service->priority = (int) ($row['priority'] ?? 100);
                $service->save();
            }

            ProviderService::query()
                ->where('provider_id', $provider->id)
                ->when($types !== [], fn ($query) => $query->whereNotIn('service_type', $types), fn ($query) => $query)
                ->delete();
        });

        ($this->audit)(
            'provider.services.synced',
            $actor,
            Provider::class,
            $provider->id,
            null,
            ['service_types' => array_column($services, 'service_type')],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $provider->load('services');
    }
}
