<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\EsimAccess;

use Illuminate\Support\Str;

/**
 * Official Partner HMAC from docs.esimaccess.com (collection folder "API").
 *
 * signStr = RT-Timestamp + RT-RequestID + RT-AccessCode + requestBody
 * sign    = HMAC-SHA256(signStr, secretKey) lowercase hex
 */
final class EsimAccessSigner
{
    /**
     * @return array<string, string>
     */
    public function headers(string $accessCode, string $secretKey, string $requestBody, ?string $timestampMs = null, ?string $requestId = null): array
    {
        $timestamp = $timestampMs ?? (string) (int) floor(microtime(true) * 1000);
        $requestId = $requestId ?? (string) Str::uuid();
        $signStr = $timestamp.$requestId.$accessCode.$requestBody;

        return [
            'RT-AccessCode' => $accessCode,
            'RT-Timestamp' => $timestamp,
            'RT-RequestID' => $requestId,
            'RT-Signature' => hash_hmac('sha256', $signStr, $secretKey),
        ];
    }
}
