<?php

declare(strict_types=1);

namespace App\Enums;

enum ProviderType: string
{
    case Esim = 'esim';
    case Sim = 'sim';
    case Flight = 'flight';
    case Hotel = 'hotel';
    case Car = 'car';
    case Insurance = 'insurance';
    case Bus = 'bus';
    case Payment = 'payment';
}
