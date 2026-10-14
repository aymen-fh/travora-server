<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Drivers\Template;

use App\Domain\MoneyMinor;
use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Integrations\Payments\Contracts\PaymentGateway;
use App\Integrations\Payments\DTOs\ChargeRequest;
use App\Integrations\Payments\DTOs\PaymentResult;

final class TemplatePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'template';
    }

    public function charge(ChargeRequest $request): PaymentResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('payment.charge', 'template');
    }

    public function refund(string $transactionId, MoneyMinor $amount, string $idempotencyKey): PaymentResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('payment.refund', 'template');
    }

    public function verify(string $transactionId): PaymentResult
    {
        throw ProviderNotImplementedException::untilOfficialDocs('payment.verify', 'template');
    }
}
