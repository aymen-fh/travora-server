<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Actions\Catalog\QuoteEsimPlan;
use App\Enums\EsimOperationStatus;
use App\Enums\EsimProfileStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimRenewal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestEsimRenewal
{
    public function __construct(
        private readonly QuoteEsimPlan $quote,
    ) {}

    public function __invoke(User $user, EsimProfile $profile, EsimPlan $newPlan, string $idempotencyKey): EsimRenewal
    {
        if ($profile->user_id !== $user->id) {
            throw new ForbiddenOperationException('eSIM profile does not belong to the actor.');
        }

        if (in_array($profile->status, [EsimProfileStatus::Cancelled, EsimProfileStatus::Failed], true)) {
            throw new ForbiddenOperationException('This eSIM cannot be renewed.');
        }

        $existing = EsimRenewal::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $price = ($this->quote)($newPlan);

        return DB::transaction(function () use ($user, $profile, $newPlan, $idempotencyKey, $price): EsimRenewal {
            $existing = EsimRenewal::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $renewal = new EsimRenewal;
            $renewal->user_id = $user->id;
            $renewal->esim_profile_id = $profile->id;
            $renewal->current_plan_id = $profile->plan_id;
            $renewal->new_plan_id = $newPlan->id;
            $renewal->provider_id = $newPlan->provider_id;
            $renewal->idempotency_key = $idempotencyKey;
            $renewal->status = EsimOperationStatus::Pending;
            $renewal->amount_minor = $price->price_minor;
            $renewal->currency = $price->currency;
            $renewal->save();

            return $renewal;
        });
    }
}
