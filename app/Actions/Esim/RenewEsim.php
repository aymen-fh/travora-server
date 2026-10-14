<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimRenewal;
use App\Models\User;
use App\Services\Esim\CompleteEsimRenewal;

final class RenewEsim
{
    public function __construct(
        private readonly CompleteEsimRenewal $complete,
    ) {}

    public function __invoke(User $user, EsimProfile $profile, EsimPlan $newPlan, string $idempotencyKey): EsimRenewal
    {
        return ($this->complete)($user, $profile, $newPlan, $idempotencyKey);
    }
}
