<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Wallet\ExpireWalletHold;
use App\Enums\WalletHoldStatus;
use App\Models\WalletHold;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExpireWalletHoldsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return 'expire-wallet-holds';
    }

    public function handle(ExpireWalletHold $expire): void
    {
        $ttlMinutes = max(1, (int) config('esim.hold_ttl_minutes', 30));
        $cutoff = now()->subMinutes($ttlMinutes);

        WalletHold::query()
            ->where('status', WalletHoldStatus::Active)
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($expired): void {
                    $expired->whereNotNull('expires_at')->where('expires_at', '<', now());
                })->orWhere(function ($stale) use ($cutoff): void {
                    $stale->whereNull('expires_at')->where('created_at', '<', $cutoff);
                });
            })
            ->orderBy('id')
            ->each(function (WalletHold $hold) use ($expire): void {
                $expire($hold, 'hold-expire-'.$hold->id);
            });
    }
}
