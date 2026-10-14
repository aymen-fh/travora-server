<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\EsimProfileStatus;
use App\Exceptions\CatalogUnavailableException;
use App\Exceptions\ForbiddenOperationException;
use App\Integrations\Esim\Manager\ProviderManager;
use App\Models\EsimProfile;
use App\Models\Provider;
use App\Models\User;

final class SendEsimSms
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(
        User $actor,
        EsimProfile $profile,
        string $message,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        if (! in_array($profile->status, [EsimProfileStatus::Installed, EsimProfileStatus::Activated], true)) {
            throw new ForbiddenOperationException('SMS can be sent only to an installed eSIM.');
        }

        $external = $profile->external_profile_id;
        if ($external === null || $external === '') {
            throw new CatalogUnavailableException('eSIM has no provider profile.');
        }

        $provider = Provider::query()->find($profile->provider_id);
        if ($provider === null) {
            throw new CatalogUnavailableException('eSIM provider is missing.');
        }

        $this->providers->partner($provider)->sendSms($external, $message);

        ($this->audit)(
            'esim.profile.sms_sent',
            $actor,
            EsimProfile::class,
            $profile->id,
            null,
            ['message' => $message],
            $ipAddress,
            $userAgent,
        );
    }
}
