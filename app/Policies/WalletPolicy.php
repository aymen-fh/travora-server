<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

final class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('wallets.view') || $user->can('wallet.view');
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $wallet->user_id === $user->id
            || $user->can('wallets.view')
            || $user->can('wallet.view')
            || $user->can('wallets.view_detail');
    }

    public function viewTransactions(User $user, Wallet $wallet): bool
    {
        return $wallet->user_id === $user->id
            || $user->can('wallets.transactions.view')
            || $user->can('wallets.view_detail');
    }

    public function viewHolds(User $user, Wallet $wallet): bool
    {
        return $wallet->user_id === $user->id
            || $user->can('wallets.holds.view')
            || $user->can('wallets.view_detail');
    }

    public function viewLedger(User $user, Wallet $wallet): bool
    {
        return $user->can('wallets.ledger.view') || $user->can('wallets.view_detail');
    }

    public function adjust(User $user, ?Wallet $wallet = null): bool
    {
        return $user->can('wallets.adjust') || $user->can('wallet.adjust') || $user->can('wallets.manage');
    }

    public function topup(User $user, Wallet $wallet): bool
    {
        return $user->can('wallets.adjust') || $user->can('wallet.adjust') || $user->can('wallets.manage');
    }
}
