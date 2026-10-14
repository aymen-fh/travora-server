<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Domain\EsimUsageSnapshot;
use App\Models\EsimProfile;
use App\Models\EsimUsage;
use App\Models\EsimUsageDaily;
use Illuminate\Support\Facades\DB;

final class RecordEsimUsage
{
    public function __invoke(EsimProfile $profile, EsimUsageSnapshot $snapshot): EsimUsage
    {
        return DB::transaction(function () use ($profile, $snapshot): EsimUsage {
            $locked = EsimProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            $usage = new EsimUsage;
            $usage->esim_profile_id = $locked->id;
            $usage->recorded_at = now();
            $usage->used_mb = $snapshot->usedMb;
            $usage->remaining_mb = $snapshot->remainingMb;
            $usage->source = $snapshot->source;
            $usage->save();

            $daily = EsimUsageDaily::query()
                ->where('esim_profile_id', $locked->id)
                ->whereDate('usage_date', now()->toDateString())
                ->lockForUpdate()
                ->first();

            if ($daily === null) {
                $daily = new EsimUsageDaily;
                $daily->esim_profile_id = $locked->id;
                $daily->usage_date = now()->toDateString();
            }

            $daily->used_mb = $snapshot->usedMb;
            $daily->remaining_mb = $snapshot->remainingMb;
            $daily->save();

            $locked->data_remaining_mb = $snapshot->remainingMb;
            if ($locked->data_total_mb === null && $snapshot->remainingMb !== null) {
                $locked->data_total_mb = $snapshot->usedMb + $snapshot->remainingMb;
            }
            $locked->last_synced_at = now();
            $locked->save();

            return $usage;
        });
    }
}
