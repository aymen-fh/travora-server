<?php

declare(strict_types=1);

namespace App\Services\Esim;

use App\Actions\Esim\ApplyEsimTopup;
use App\Actions\Esim\FailEsimTopup;
use App\Actions\Esim\RequestEsimTopup;
use App\Enums\EsimOperationStatus;
use App\Exceptions\CapabilityNotSupportedException;
use App\Exceptions\CatalogUnavailableException;
use App\Integrations\Esim\DTOs\TopUpRequest;
use App\Integrations\Esim\Exceptions\ProviderException;
use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Enums\ProviderCapability;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimTopup;
use App\Models\Provider;
use App\Models\User;
use App\Queries\Catalog\FindProviderPlanExternalId;
use InvalidArgumentException;

final class CompleteEsimTopup
{
    public function __construct(
        private readonly RequestEsimTopup $request,
        private readonly ApplyEsimTopup $apply,
        private readonly FailEsimTopup $fail,
        private readonly ProviderManager $providers,
        private readonly FindProviderPlanExternalId $externalPlanId,
    ) {}

    public function __invoke(User $user, EsimProfile $profile, EsimPlan $plan, string $idempotencyKey): EsimTopup
    {
        $topup = ($this->request)($user, $profile, $plan, $idempotencyKey);

        if (in_array($topup->status, [EsimOperationStatus::Completed, EsimOperationStatus::Failed, EsimOperationStatus::Cancelled], true)) {
            return $topup;
        }

        if ($topup->status === EsimOperationStatus::Processing) {
            return $topup;
        }

        $topup->status = EsimOperationStatus::Processing;
        $topup->save();

        $profile->loadMissing('provider');
        $externalPlanId = ($this->externalPlanId)($plan);

        if ($profile->external_profile_id === null || $profile->external_profile_id === '' || $externalPlanId === null) {
            return ($this->fail)($topup, 'Top-up is not mapped to a provider product.');
        }

        $provider = Provider::query()->find($profile->provider_id);
        if ($provider === null) {
            return ($this->fail)($topup, 'eSIM provider is missing.');
        }

        try {
            $driver = $this->providers->driver($provider);
            $this->providers->assertCapability($driver, ProviderCapability::Topup);
            $result = $driver->topUp(new TopUpRequest(
                $profile->external_profile_id,
                $externalPlanId,
                $idempotencyKey,
            ));
        } catch (ProviderTimeoutException) {
            return $topup->fresh() ?? $topup;
        } catch (ProviderException|CatalogUnavailableException|CapabilityNotSupportedException|InvalidArgumentException $exception) {
            return ($this->fail)($topup, $exception->getMessage());
        }

        return ($this->apply)($topup->fresh() ?? $topup, $result);
    }
}
