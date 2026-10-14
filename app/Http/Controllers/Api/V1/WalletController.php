<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Wallet\CreditWallet;
use App\Domain\MoneyMinor;
use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\TopUpWalletRequest;
use App\Http\Resources\WalletResource;
use App\Http\Responses\ApiResponse;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $wallet = $this->wallet($request);
        $this->authorize('view', $wallet);

        return ApiResponse::success(new WalletResource($wallet));
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->wallet($request);
        $this->authorize('viewTransactions', $wallet);

        $page = $wallet->transactions()->latest()->paginate($request->integer('per_page', 20));

        $data = collect($page->items())->map(fn ($tx): array => [
            'id' => $tx->id,
            'type' => $tx->type,
            'direction' => $tx->direction?->value,
            'amount_minor' => $tx->amount_minor,
            'balance_after_minor' => $tx->balance_after_minor,
            'status' => $tx->status?->value,
            'description' => $tx->description,
            'created_at' => $tx->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success($data, 'OK', 200, [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function topup(TopUpWalletRequest $request, CreditWallet $credit): JsonResponse
    {
        $wallet = $this->wallet($request, $request->string('currency', 'LYD')->toString() ?: 'LYD');
        $this->authorize('topup', $wallet);

        $idempotencyKey = $request->header('Idempotency-Key');
        if (! $idempotencyKey) {
            abort(428, 'Idempotency-Key header is required for wallet top-up.');
        }

        $credit(
            $request->user(),
            $wallet,
            MoneyMinor::of((int) $request->integer('amount_minor'), $wallet->currency),
            WalletTransactionType::Topup,
            'Wallet top-up',
            $idempotencyKey,
        );

        return ApiResponse::success(new WalletResource($wallet->fresh()), 'Wallet credited');
    }

    private function wallet(Request $request, string $currency = 'LYD'): Wallet
    {
        return Wallet::query()
            ->where('user_id', $request->user()->id)
            ->where('currency', strtoupper($currency))
            ->firstOrFail();
    }
}
