<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Wallet\AdjustWallet;
use App\Domain\MoneyMinor;
use App\Enums\WalletDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustWalletRequest;
use App\Http\Resources\Admin\AdminWalletResource;
use App\Http\Responses\ApiResponse;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Wallet::class);

        $page = Wallet::query()
            ->with('user:id,email')
            ->when($request->filled('currency'), fn ($query) => $query->where('currency', $request->string('currency')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminWalletResource::collection($page->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        );
    }

    public function show(Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        return ApiResponse::success(new AdminWalletResource($wallet->load('user:id,email')));
    }

    public function transactions(Wallet $wallet): JsonResponse
    {
        $this->authorize('viewTransactions', $wallet);

        $items = $wallet->transactions()->latest()->limit(100)->get()->map(fn ($transaction): array => [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'status' => $transaction->status?->value,
            'direction' => $transaction->direction?->value,
            'amount_minor' => $transaction->amount_minor,
            'balance_after_minor' => $transaction->balance_after_minor,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success($items);
    }

    public function holds(Wallet $wallet): JsonResponse
    {
        $this->authorize('viewHolds', $wallet);

        $items = $wallet->holds()->latest()->get()->map(fn ($hold): array => [
            'id' => $hold->id,
            'amount_minor' => $hold->amount_minor,
            'currency' => $hold->currency,
            'status' => $hold->status?->value,
            'reference_type' => $hold->reference_type,
            'reference_id' => $hold->reference_id,
            'expires_at' => $hold->expires_at?->toIso8601String(),
        ]);

        return ApiResponse::success($items);
    }

    public function ledger(Wallet $wallet): JsonResponse
    {
        $this->authorize('viewLedger', $wallet);

        $items = WalletLedgerEntry::query()
            ->whereHas('transaction', fn ($query) => $query->where('wallet_id', $wallet->id))
            ->with('account:id,code,name')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn (WalletLedgerEntry $entry): array => [
                'id' => $entry->id,
                'wallet_transaction_id' => $entry->wallet_transaction_id,
                'account' => $entry->account?->code,
                'debit_minor' => $entry->debit_minor,
                'credit_minor' => $entry->credit_minor,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success($items);
    }

    public function adjust(AdjustWalletRequest $request, Wallet $wallet, AdjustWallet $adjust): JsonResponse
    {
        $this->authorize('adjust', $wallet);

        $transaction = $adjust(
            $request->user(),
            $wallet,
            WalletDirection::from($request->string('direction')->toString()),
            MoneyMinor::of((int) $request->integer('amount_minor'), $wallet->currency),
            $request->string('reason')->toString(),
            $request->string('idempotency_key')->toString(),
            $request,
        );

        return ApiResponse::success([
            'wallet' => (new AdminWalletResource($wallet->refresh()->load('user:id,email')))->resolve(),
            'transaction_id' => $transaction->id,
            'balance_after_minor' => $transaction->balance_after_minor,
        ], 'Wallet adjusted');
    }
}
