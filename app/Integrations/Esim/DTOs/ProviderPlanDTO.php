<?php

declare(strict_types=1);

namespace App\Integrations\Esim\DTOs;

final readonly class ProviderPlanDTO
{
    /**
     * @param  list<string>  $countryIso2
     */
    public function __construct(
        public string $externalPlanId,
        public string $name,
        public int $dataMb,
        public bool $isUnlimited,
        public int $validityDays,
        public int $costMinor,
        public string $currency,
        public array $countryIso2 = [],
        public ?string $description = null,
    ) {}
}
