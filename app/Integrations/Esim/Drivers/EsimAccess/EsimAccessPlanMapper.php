<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\EsimAccess;

use App\Integrations\Esim\DTOs\ProviderCoverageDTO;
use App\Integrations\Esim\DTOs\ProviderPlanDTO;

final class EsimAccessPlanMapper
{
    /**
     * @param  array<string, mixed>  $package
     */
    public function plan(array $package): ProviderPlanDTO
    {
        $code = (string) ($package['slug'] ?? $package['packageCode'] ?? '');
        $volume = (int) ($package['volume'] ?? 0);
        $price = (int) ($package['price'] ?? 0);
        $location = (string) ($package['location'] ?? $package['locationCode'] ?? '');
        $dataType = (int) ($package['dataType'] ?? 1);

        return new ProviderPlanDTO(
            externalPlanId: $code,
            name: (string) ($package['name'] ?? $code),
            dataMb: $this->bytesToMb($volume),
            isUnlimited: $dataType === 4,
            validityDays: (int) ($package['duration'] ?? 0),
            costMinor: intdiv($price, 100),
            currency: (string) ($package['currencyCode'] ?? 'USD'),
            countryIso2: $this->countries($location),
            description: isset($package['description']) ? (string) $package['description'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $location
     */
    public function coverage(array $location): ?ProviderCoverageDTO
    {
        $code = strtoupper((string) ($location['code'] ?? ''));

        if (strlen($code) !== 2) {
            return null;
        }

        return new ProviderCoverageDTO(
            iso2: $code,
            name: (string) ($location['name'] ?? $code),
        );
    }

    public function bytesToMb(int $bytes): int
    {
        return intdiv(max(0, $bytes), 1048576);
    }

    /**
     * @return list<string>
     */
    private function countries(string $location): array
    {
        $codes = [];

        foreach (preg_split('/\s*,\s*/', $location) ?: [] as $part) {
            $code = strtoupper($part);
            if (strlen($code) === 2) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }
}
