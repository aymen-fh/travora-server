<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Actions\Catalog\QuoteEsimPlan;
use App\Enums\EsimOperationStatus;
use App\Enums\EsimProfileStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimTopup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestEsimTopup
{
    public function __construct(
        private readonly QuoteEsimPlan $quote,
    ) {}

    public function __invoke(User $user, EsimProfile $profile, EsimPlan $plan, string $idempotencyKey): EsimTopup
    {
        if ($profile->user_id !== $user->id) {
            throw new ForbiddenOperationException('eSIM profile does not belong to the actor.');
        }

        if (in_array($profile->status, [EsimProfileStatus::Expired, EsimProfileStatus::Cancelled, EsimProfileStatus::Failed], true)) {
            throw new ForbiddenOperationException('This eSIM cannot receive a top-up.');
        }

        $existing = EsimTopup::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $price = ($this->quote)($plan);

        return DB::transaction(function () use ($user, $profile, $plan, $idempotencyKey, $price): EsimTopup {
            $existing = EsimTopup::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $topup = new EsimTopup;
            $topup->user_id = $user->id;
            $topup->esim_profile_id = $profile->id;
            $topup->provider_id = $profile->provider_id;
            $topup->idempotency_key = $idempotencyKey;
            $topup->data_mb = $plan->data_mb;
            $topup->is_unlimited = $plan->is_unlimited;
            $topup->amount_minor = $price->price_minor;
            $topup->currency = $price->currency;
            $topup->status = EsimOperationStatus::Pending;
            $topup->save();

            return $topup;
        });
    }
}
