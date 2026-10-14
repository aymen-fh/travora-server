<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Contracts;

/**
 * Official Partner operations that sit beside the shared EsimProvider port.
 * Paths and bodies come from docs.esimaccess.com.
 */
interface PartnerEsimOperations
{
    public function cancelProfile(string $esimTranNo): void;

    public function suspendProfile(string $esimTranNo): void;

    public function unsuspendProfile(string $esimTranNo): void;

    public function revokeProfile(string $esimTranNo): void;

    public function sendSms(string $esimTranNo, string $message): void;

    public function merchantBalance(): int;

    /**
     * @return array<string, mixed>
     */
    public function discountRules(string $discountRuleCode): array;

    public function saveWebhook(string $url): void;

    /**
     * @return array<string, mixed>
     */
    public function queryWebhook(): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listRawPackages(array $filters = []): array;
}
