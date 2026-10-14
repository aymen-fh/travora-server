<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\EsimAccess;

use App\Domain\EsimFulfillmentResult;
use DateTimeImmutable;

final class EsimAccessFulfillmentMapper
{
    /**
     * @param  array<string, mixed>  $esim
     */
    public function fulfillment(array $esim): ?EsimFulfillmentResult
    {
        $profileId = (string) ($esim['esimTranNo'] ?? '');
        $activation = (string) ($esim['ac'] ?? '');

        if ($profileId === '' || $activation === '') {
            return null;
        }

        [$smdp, $matching] = $this->parseLpa($activation);
        $total = isset($esim['totalVolume']) ? (int) $esim['totalVolume'] : null;
        $used = isset($esim['orderUsage']) ? (int) $esim['orderUsage'] : null;
        $expires = isset($esim['expiredTime']) && is_string($esim['expiredTime']) && $esim['expiredTime'] !== ''
            ? new DateTimeImmutable($esim['expiredTime'])
            : null;

        return new EsimFulfillmentResult(
            externalProfileId: $profileId,
            iccid: isset($esim['iccid']) && $esim['iccid'] !== '' ? (string) $esim['iccid'] : null,
            matchingId: $matching,
            smdpAddress: $smdp,
            activationCode: $activation,
            dataTotalMb: $total === null ? null : intdiv(max(0, $total), 1048576),
            dataRemainingMb: ($total === null || $used === null) ? null : intdiv(max(0, $total - $used), 1048576),
            expiresAt: $expires,
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseLpa(string $activation): array
    {
        if (! str_starts_with($activation, 'LPA:')) {
            return [null, null];
        }

        $parts = explode('$', $activation);

        return [$parts[1] ?? null, $parts[2] ?? null];
    }
}
