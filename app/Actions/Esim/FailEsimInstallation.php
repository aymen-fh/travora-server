<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimProfileStatus;
use App\Enums\InstallationStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimInstallation;
use App\Models\EsimProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class FailEsimInstallation
{
    public function __invoke(User $user, EsimProfile $profile, string $reason): EsimInstallation
    {
        if ($profile->user_id !== $user->id) {
            throw new ForbiddenOperationException('eSIM profile does not belong to the actor.');
        }

        if ($profile->status === EsimProfileStatus::Installed || $profile->status === EsimProfileStatus::Activated) {
            throw new ForbiddenOperationException('Installed eSIMs cannot record a failed installation.');
        }

        return DB::transaction(function () use ($profile, $reason): EsimInstallation {
            $installation = new EsimInstallation;
            $installation->esim_profile_id = $profile->id;
            $installation->method = 'qr';
            $installation->status = InstallationStatus::Failed->value;
            $installation->started_at = now();
            $installation->completed_at = now();
            $installation->failure_reason = $reason;
            $installation->save();

            return $installation;
        });
    }
}
