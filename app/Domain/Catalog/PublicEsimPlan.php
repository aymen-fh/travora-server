<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

final readonly class PublicEsimPlan
{
    /**
     * @param  list<array{iso2: string, name: string}>  $countries
     * @param  list<array{name: string}>  $networks
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?int $dataMb,
        public bool $isUnlimited,
        public int $validityDays,
        public bool $hotspotSupported,
        public bool $isFeatured,
        public string $currency,
        public int $priceMinor,
        public array $countries,
        public array $networks,
    ) {}
}
