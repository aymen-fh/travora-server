<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimTopup;
use App\Models\User;
use App\Services\Esim\CompleteEsimTopup;

final class TopUpEsim
{
    public function __construct(
        private readonly CompleteEsimTopup $complete,
    ) {}

    public function __invoke(User $user, EsimProfile $profile, EsimPlan $plan, string $idempotencyKey): EsimTopup
    {
        return ($this->complete)($user, $profile, $plan, $idempotencyKey);
    }
}
