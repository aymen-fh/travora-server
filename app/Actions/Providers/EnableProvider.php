<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\RecordStatus;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\Request;

final class EnableProvider
{
    public function __construct(
        private readonly RecordAuditLog $audit,
    ) {}

    public function __invoke(User $actor, Provider $provider, ?Request $request = null): Provider
    {
        $old = ['status' => $provider->status?->value];
        $provider->status = RecordStatus::Active;
        $provider->save();

        ($this->audit)(
            'provider.enabled',
            $actor,
            Provider::class,
            $provider->id,
            $old,
            ['status' => RecordStatus::Active->value],
            $request?->ip(),
            $request?->userAgent(),
        );

        return $provider;
    }
}
