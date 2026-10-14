<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Domain\EsimUsageSnapshot;
use App\Enums\EsimProfileStatus;
use App\Enums\ProviderCapability;
use App\Exceptions\CatalogUnavailableException;
use App\Exceptions\ForbiddenOperationException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\EsimProfile;
use App\Models\EsimUsage;
use App\Models\Provider;

final class RefreshEsimUsage
{
    public function __construct(
        private readonly RecordEsimUsage $record,
        private readonly ProviderManager $providers,
    ) {}

    public function __invoke(EsimProfile $profile, ?EsimUsageSnapshot $snapshot = null): EsimUsage
    {
        if ($snapshot !== null) {
            return ($this->record)($profile, $snapshot);
        }

        if (! in_array($profile->status, [
            EsimProfileStatus::Ready,
            EsimProfileStatus::Installed,
            EsimProfileStatus::Activated,
        ], true)) {
            throw new ForbiddenOperationException('Usage cannot be refreshed for this eSIM.');
        }

        if ($profile->external_profile_id === null || $profile->external_profile_id === '') {
            throw new CatalogUnavailableException('eSIM has no provider profile to sync.');
        }

        $provider = Provider::query()->find($profile->provider_id);
        if ($provider === null) {
            throw new CatalogUnavailableException('eSIM provider is missing.');
        }

        $driver = $this->providers->driver($provider);
        $this->providers->assertCapability($driver, ProviderCapability::Usage);

        return ($this->record)($profile, $driver->getUsage($profile->external_profile_id));
    }
}
