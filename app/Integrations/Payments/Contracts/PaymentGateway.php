<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Contracts;

use App\Domain\MoneyMinor;
use App\Integrations\Payments\DTOs\ChargeRequest;
use App\Integrations\Payments\DTOs\PaymentResult;

interface PaymentGateway
{
    public function name(): string;

    public function charge(ChargeRequest $request): PaymentResult;

    public function refund(string $transactionId, MoneyMinor $amount, string $idempotencyKey): PaymentResult;

    public function verify(string $transactionId): PaymentResult;
}
