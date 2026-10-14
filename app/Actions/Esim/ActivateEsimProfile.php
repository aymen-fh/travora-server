<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimProfileStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimActivationEvent;
use App\Models\EsimProfile;
use App\Models\User;
use App\States\EsimStateMachine;
use Illuminate\Support\Facades\DB;

final class ActivateEsimProfile
{
    public function __invoke(User $user, EsimProfile $profile): EsimProfile
    {
        if ($profile->user_id !== $user->id) {
            throw new ForbiddenOperationException('eSIM profile does not belong to the actor.');
        }

        if ($profile->status === EsimProfileStatus::Activated) {
            return $profile;
        }

        return DB::transaction(function () use ($profile): EsimProfile {
            $locked = EsimProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === EsimProfileStatus::Activated) {
                return $locked;
            }

            EsimStateMachine::assert($locked->status, EsimProfileStatus::Activated);

            $locked->status = EsimProfileStatus::Activated;
            $locked->activated_at = now();
            $locked->valid_from = $locked->valid_from ?? now();
            $locked->save();

            $event = new EsimActivationEvent;
            $event->esim_profile_id = $locked->id;
            $event->event_type = 'activated';
            $event->status = 'activated';
            $event->save();

            return $locked;
        });
    }
}
