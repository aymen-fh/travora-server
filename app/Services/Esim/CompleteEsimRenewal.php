<?php

declare(strict_types=1);

namespace App\Services\Esim;

use App\Actions\Esim\ApplyEsimRenewal;
use App\Actions\Esim\FailEsimRenewal;
use App\Actions\Esim\RequestEsimRenewal;
use App\Enums\EsimOperationStatus;
use App\Enums\ProviderCapability;
use App\Exceptions\CapabilityNotSupportedException;
use App\Exceptions\CatalogUnavailableException;
use App\Integrations\Esim\DTOs\RenewRequest;
use App\Integrations\Esim\Exceptions\ProviderException;
use App\Integrations\Esim\Exceptions\ProviderTimeoutException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimRenewal;
use App\Models\Provider;
use App\Models\User;
use App\Queries\Catalog\FindProviderPlanExternalId;
use InvalidArgumentException;

final class CompleteEsimRenewal
{
    public function __construct(
        private readonly RequestEsimRenewal $request,
        private readonly ApplyEsimRenewal $apply,
        private readonly FailEsimRenewal $fail,
        private readonly ProviderManager $providers,
        private readonly FindProviderPlanExternalId $externalPlanId,
    ) {}

    public function __invoke(User $user, EsimProfile $profile, EsimPlan $newPlan, string $idempotencyKey): EsimRenewal
    {
        $renewal = ($this->request)($user, $profile, $newPlan, $idempotencyKey);

        if (in_array($renewal->status, [EsimOperationStatus::Completed, EsimOperationStatus::Failed, EsimOperationStatus::Cancelled], true)) {
            return $renewal;
        }

        if ($renewal->status === EsimOperationStatus::Processing) {
            return $renewal;
        }

        $renewal->status = EsimOperationStatus::Processing;
        $renewal->save();

        $externalPlanId = ($this->externalPlanId)($newPlan);

        if ($profile->external_profile_id === null || $profile->external_profile_id === '' || $externalPlanId === null) {
            return ($this->fail)($renewal, 'Renewal is not mapped to a provider product.');
        }

        $provider = Provider::query()->find($profile->provider_id);
        if ($provider === null) {
            return ($this->fail)($renewal, 'eSIM provider is missing.');
        }

        try {
            $driver = $this->providers->driver($provider);
            $this->providers->assertCapability($driver, ProviderCapability::Renewal);
            $result = $driver->renew(new RenewRequest(
                $profile->external_profile_id,
                $externalPlanId,
                $idempotencyKey,
            ));
        } catch (ProviderTimeoutException) {
            return $renewal->fresh() ?? $renewal;
        } catch (ProviderException|CatalogUnavailableException|CapabilityNotSupportedException|InvalidArgumentException $exception) {
            return ($this->fail)($renewal, $exception->getMessage());
        }

        return ($this->apply)($renewal->fresh() ?? $renewal, $result);
    }
}
