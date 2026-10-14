<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Esim\ExpireEsimProfile;
use App\Enums\EsimProfileStatus;
use App\Models\EsimProfile;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExpireEsimProfilesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'expire-esim-profiles';
    }

    public function handle(ExpireEsimProfile $expire): void
    {
        EsimProfile::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNotIn('status', [EsimProfileStatus::Expired, EsimProfileStatus::Cancelled])
            ->orderBy('id')
            ->each(function (EsimProfile $profile) use ($expire): void {
                $expire($profile);
            });
    }
}
