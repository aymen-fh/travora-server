<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimOperationStatus;
use App\Integrations\Esim\DTOs\ProviderRenewResult;
use App\Models\EsimActivationEvent;
use App\Models\EsimProfile;
use App\Models\EsimRenewal;
use Illuminate\Support\Facades\DB;

final class ApplyEsimRenewal
{
    public function __invoke(EsimRenewal $renewal, ProviderRenewResult $result): EsimRenewal
    {
        if ($renewal->status === EsimOperationStatus::Completed) {
            return $renewal;
        }

        return DB::transaction(function () use ($renewal, $result): EsimRenewal {
            $locked = EsimRenewal::query()->whereKey($renewal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === EsimOperationStatus::Completed) {
                return $locked;
            }

            $profile = EsimProfile::query()->whereKey($locked->esim_profile_id)->lockForUpdate()->firstOrFail();
            $days = $result->validityDaysAdded ?? 0;

            $locked->provider_reference = $result->externalReference;
            $locked->status = EsimOperationStatus::Completed;
            $locked->effective_at = now();
            $locked->save();

            $profile->plan_id = $locked->new_plan_id;
            if ($days > 0) {
                $base = $profile->expires_at !== null
                    ? $profile->expires_at->toMutable()
                    : now();
                $profile->expires_at = $base->addDays($days);
            }
            $profile->save();

            $event = new EsimActivationEvent;
            $event->esim_profile_id = $profile->id;
            $event->event_type = 'renewal_applied';
            $event->status = 'completed';
            $event->provider_reference = $result->externalReference;
            $event->message = 'Renewal applied';
            $event->save();

            return $locked->fresh() ?? $locked;
        });
    }
}
