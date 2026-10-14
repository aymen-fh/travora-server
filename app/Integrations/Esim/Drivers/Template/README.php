<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\Template;

/**
 * Copy this folder when a real provider's official API documentation is supplied.
 *
 * Required files (names are local; do not invent vendor paths):
 *  - {Name}Client.php      HTTP only, using URLs/auth/signing from the official docs.
 *  - {Name}Provider.php    Implements EsimProvider and maps responses into Travora DTOs.
 *  - {Name}PlanMapper.php
 *  - {Name}FulfillmentMapper.php
 *
 * Rules:
 *  - findPurchase() must be a lookup, never a second purchase.
 *  - Credentials are secret references only (ProviderSecretStore).
 *  - Do not copy FakeProvider behavior into a real driver.
 *  - Do not invent endpoints, headers, error codes, or webhook signatures.
 *
 * Then:
 *  1. Register the class in config/esim.php under the provider `code`.
 *  2. Insert the providers row (code must match the config key).
 *  3. Store credential secret references via admin, never plaintext API keys in application code.
 */
final class README {}
