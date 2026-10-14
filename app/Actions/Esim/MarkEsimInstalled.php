<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimProfileStatus;
use App\Enums\InstallationStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimInstallation;
use App\Models\EsimProfile;
use App\Models\User;
use App\States\EsimStateMachine;
use Illuminate\Support\Facades\DB;

final class MarkEsimInstalled
{
    public function __invoke(User $user, EsimProfile $profile): EsimProfile
    {
        if ($profile->user_id !== $user->id) {
            throw new ForbiddenOperationException('eSIM profile does not belong to the actor.');
        }

        if ($profile->status === EsimProfileStatus::Installed || $profile->status === EsimProfileStatus::Activated) {
            return $profile;
        }

        return DB::transaction(function () use ($profile): EsimProfile {
            $locked = EsimProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [EsimProfileStatus::Installed, EsimProfileStatus::Activated], true)) {
                return $locked;
            }

            EsimStateMachine::assert($locked->status, EsimProfileStatus::Installed);

            $locked->status = EsimProfileStatus::Installed;
            $locked->installed_at = now();
            $locked->save();

            $installation = new EsimInstallation;
            $installation->esim_profile_id = $locked->id;
            $installation->method = 'qr';
            $installation->status = InstallationStatus::Installed->value;
            $installation->started_at = now();
            $installation->completed_at = now();
            $installation->save();

            return $locked;
        });
    }
}
