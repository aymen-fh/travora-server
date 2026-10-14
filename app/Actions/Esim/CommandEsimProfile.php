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
use App\States\EsimStateMachine;
use Illuminate\Support\Facades\DB;

final class CommandEsimProfile
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(
        User $actor,
        EsimProfile $profile,
        string $command,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): EsimProfile {
        $target = match ($command) {
            'cancel', 'revoke' => EsimProfileStatus::Cancelled,
            'suspend' => EsimProfileStatus::Suspended,
            'unsuspend' => EsimProfileStatus::Activated,
            default => throw new ForbiddenOperationException('Unknown eSIM command.'),
        };

        EsimStateMachine::assert($profile->status, $target);

        $external = $profile->external_profile_id;
        if ($external === null || $external === '') {
            throw new CatalogUnavailableException('eSIM has no provider profile.');
        }

        $provider = Provider::query()->find($profile->provider_id);
        if ($provider === null) {
            throw new CatalogUnavailableException('eSIM provider is missing.');
        }

        $partner = $this->providers->partner($provider);

        match ($command) {
            'cancel' => $partner->cancelProfile($external),
            'suspend' => $partner->suspendProfile($external),
            'unsuspend' => $partner->unsuspendProfile($external),
            'revoke' => $partner->revokeProfile($external),
            default => throw new ForbiddenOperationException('Unknown eSIM command.'),
        };

        $from = $profile->status->value;

        return DB::transaction(function () use ($profile, $target, $command, $actor, $from, $ipAddress, $userAgent): EsimProfile {
            $locked = EsimProfile::query()->lockForUpdate()->findOrFail($profile->id);
            EsimStateMachine::assert($locked->status, $target);
            $locked->status = $target;
            $locked->last_synced_at = now();
            if ($command === 'unsuspend' && $locked->activated_at === null) {
                $locked->activated_at = now();
            }
            $locked->save();

            ($this->audit)(
                'esim.profile.'.$command,
                $actor,
                EsimProfile::class,
                $locked->id,
                ['status' => $from],
                ['status' => $target->value],
                $ipAddress,
                $userAgent,
            );

            return $locked;
        });
    }
}
