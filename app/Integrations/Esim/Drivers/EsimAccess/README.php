<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Drivers\EsimAccess;

/**
 * Real eSIMAccess driver. HTTP URLs, headers, and HMAC come only from
 * docs/ESIMACCESS_REST_CONTRACT.md (official Partner collection).
 *
 * findPurchase() queries /esim/query by remembered orderNo. It never POST /esim/order.
 * renew() is not implemented: no official Renewal API.
 * Profile commands, SMS, balance, discount rules, and webhook save/query use the official paths.
 */
final class README {}
