<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimProfileStatus;
use App\Models\EsimActivationEvent;
use App\Models\EsimProfile;
use App\States\EsimStateMachine;
use Illuminate\Support\Facades\DB;

final class ExpireEsimProfile
{
    public function __invoke(EsimProfile $profile): EsimProfile
    {
        if ($profile->status === EsimProfileStatus::Expired) {
            return $profile;
        }

        return DB::transaction(function () use ($profile): EsimProfile {
            $locked = EsimProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === EsimProfileStatus::Expired) {
                return $locked;
            }

            EsimStateMachine::assert($locked->status, EsimProfileStatus::Expired);

            $locked->status = EsimProfileStatus::Expired;
            $locked->save();

            $event = new EsimActivationEvent;
            $event->esim_profile_id = $locked->id;
            $event->event_type = 'expired';
            $event->status = 'expired';
            $event->message = 'eSIM validity ended';
            $event->save();

            return $locked;
        });
    }
}
