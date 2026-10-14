<?php

declare(strict_types=1);

namespace App\Enums;

enum ProviderCapability: string
{
    case Health = 'health';
    case Plans = 'plans';
    case Coverage = 'coverage';
    case Purchase = 'purchase';
    case EsimLookup = 'esim_lookup';
    case Usage = 'usage';
    case Topup = 'topup';
    case Renewal = 'renewal';
    case Webhooks = 'webhooks';
}
