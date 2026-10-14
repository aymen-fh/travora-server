<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimOperationStatus;
use App\Integrations\Esim\DTOs\ProviderTopUpResult;
use App\Models\EsimActivationEvent;
use App\Models\EsimProfile;
use App\Models\EsimTopup;
use Illuminate\Support\Facades\DB;

final class ApplyEsimTopup
{
    public function __invoke(EsimTopup $topup, ProviderTopUpResult $result): EsimTopup
    {
        if ($topup->status === EsimOperationStatus::Completed) {
            return $topup;
        }

        return DB::transaction(function () use ($topup, $result): EsimTopup {
            $locked = EsimTopup::query()->whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === EsimOperationStatus::Completed) {
                return $locked;
            }

            $profile = EsimProfile::query()->whereKey($locked->esim_profile_id)->lockForUpdate()->firstOrFail();
            $added = $result->dataMbAdded ?? $locked->data_mb ?? 0;

            $locked->provider_reference = $result->externalReference;
            $locked->status = EsimOperationStatus::Completed;
            $locked->save();

            if ($added > 0) {
                $profile->data_total_mb = ($profile->data_total_mb ?? 0) + $added;
                $profile->data_remaining_mb = ($profile->data_remaining_mb ?? 0) + $added;
                $profile->save();
            }

            $event = new EsimActivationEvent;
            $event->esim_profile_id = $profile->id;
            $event->event_type = 'topup_applied';
            $event->status = 'completed';
            $event->provider_reference = $result->externalReference;
            $event->message = 'Top-up applied';
            $event->save();

            return $locked->fresh() ?? $locked;
        });
    }
}
