<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\EsimFulfillmentResult;
use App\Models\Order;

final class ApplyProviderFulfillment
{
    public function __construct(
        private readonly ApplyEsimFulfillment $apply,
    ) {}

    public function __invoke(Order $order, EsimFulfillmentResult $result): Order
    {
        return ($this->apply)($order, $result);
    }
}
