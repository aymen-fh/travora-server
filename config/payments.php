<?php

declare(strict_types=1);

use App\Integrations\Payments\Drivers\Template\TemplatePaymentGateway;
use App\Integrations\Payments\Drivers\Wallet\WalletGateway;

return [
    'default' => env('PAYMENT_DRIVER', 'wallet'),
    'drivers' => [
        'wallet' => WalletGateway::class,
        'template' => TemplatePaymentGateway::class,
    ],
];
