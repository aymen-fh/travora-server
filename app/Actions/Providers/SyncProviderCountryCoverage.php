<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\RecordStatus;
use App\Models\Country;
use App\Models\Provider;
use App\Models\ProviderCountryCoverage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SyncProviderCountryCoverage
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  list<array{country_id: string, service_type: string, status?: string}>  $coverage
     */
    public function __invoke(User $actor, Provider $provider, array $coverage, ?Request $request = null): Provider
    {
        $countryIds = Country::query()
            ->whereIn('id', array_column($coverage, 'country_id'))
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($provider, $coverage, $countryIds): void {
            $keep = [];

            foreach ($coverage as $row) {
                $countryId = (string) $row['country_id'];
                if (! in_array($countryId, $countryIds, true)) {
                    continue;
                }

                $serviceType = (string) $row['service_type'];
                $keep[] = $countryId.'|'.$serviceType;

                $record = ProviderCountryCoverage::query()
                    ->where('provider_id', $provider->id)
                    ->where('country_id', $countryId)
                    ->where('service_type', $serviceType)
                    ->first();

                if ($record === null) {
                    $record = new ProviderCountryCoverage;
                    $record->provider_id = $provider->id;
                    $record->country_id = $countryId;
                    $record->service_type = $serviceType;
                }

                $record->status = isset($row['status'])
                    ? RecordStatus::from((string) $row['status'])
                    : RecordStatus::Active;
                $record->save();
            }

            ProviderCountryCoverage::query()
                ->where('provider_id', $provider->id)
                ->get()
                ->each(function (ProviderCountryCoverage $row) use ($keep): void {
                    if (! in_array($row->country_id.'|'.$row->service_type, $keep, true)) {
                        $row->delete();
                    }
                });
        });

        ($this->audit)(
            'provider.coverage.synced',
            $actor,
            Provider::class,
            $provider->id,
            null,
            ['rows' => count($coverage)],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $provider->load('countryCoverage.country');
    }
}
