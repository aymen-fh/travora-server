<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Esim\RefreshEsimUsage;
use App\Enums\EsimProfileStatus;
use App\Exceptions\DomainException;
use App\Integrations\Esim\Exceptions\ProviderException;
use App\Models\EsimProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;

final class SyncProviderUsageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @var list<EsimProfileStatus>
     */
    public const SYNCABLE = [
        EsimProfileStatus::Ready,
        EsimProfileStatus::Installed,
        EsimProfileStatus::Activated,
    ];

    public function handle(RefreshEsimUsage $refresh): void
    {
        EsimProfile::query()
            ->whereIn('status', self::SYNCABLE)
            ->whereNotNull('external_profile_id')
            ->orderBy('id')
            ->each(function (EsimProfile $profile) use ($refresh): void {
                try {
                    $refresh($profile);
                } catch (ProviderException|DomainException|InvalidArgumentException) {
                    return;
                }
            });
    }
}
