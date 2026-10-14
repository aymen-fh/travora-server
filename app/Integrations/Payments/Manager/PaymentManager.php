<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Manager;

use App\Integrations\Payments\Contracts\PaymentGateway;
use InvalidArgumentException;

final class PaymentManager
{
    public function driver(string $name = 'wallet'): PaymentGateway
    {
        $class = config('payments.drivers.'.$name);

        if (! is_string($class) || ! is_subclass_of($class, PaymentGateway::class)) {
            throw new InvalidArgumentException("No payment gateway registered for [{$name}].");
        }

        return app($class);
    }
}
