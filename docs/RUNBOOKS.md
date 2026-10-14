# Travora operations runbooks

These procedures use the same Core Actions already exposed by Admin and Mobile. Do not mutate wallet balances, order status, or eSIM lifecycle outside those actions.

Load-balancer liveness: `GET /up`  
Operator checks: `php artisan travora:health`  
Pre-deploy: `php artisan travora:validate-env --strict`

## Provider failure

Symptoms: health checks fail, purchases stay `processing`, Admin health is not `healthy`.

1. Confirm with `php artisan travora:health` that the API, database, cache, and queue are up.
2. In Admin, inspect the provider and recent `provider_health_checks`.
3. If credentials are wrong, replace them through the masked credential API. Never paste secrets into tickets or logs.
4. Disable the provider (`POST /api/v1/admin/providers/{id}/disable`) so catalog sync and new purchases stop. Do **not** invent automatic failover after a purchase may have succeeded.
5. Leave in-flight orders to `SweepStaleFulfillmentsJob` / Admin retry-fulfillment. A second provider buy is forbidden for the same idempotency key.
6. After the vendor recovers, enable the provider and run a single product sync.

## Payment / wallet inconsistency

Travora production payments are the internal wallet (`PAYMENT_DRIVER=wallet`). There is no card gateway in this release.

1. Never `UPDATE wallets` by hand.
2. Compare `available_balance_minor + held_balance_minor` with posted ledger debit/credit for the wallet.
3. Active holds past TTL are released by `ExpireWalletHoldsJob` via `ExpireWalletHold`.
4. Orders stuck in fulfillment after `PROVIDER_RECONCILE_TTL` are failed by `SweepStaleFulfillmentsJob`, which releases the hold. That is not an external refund.
5. Manual corrections use Admin wallet adjustment (`CreditWallet` / debit through `AdjustWallet`) with `idempotency_key` and a reason. That writes audit.

## Webhook backlog / dead letter

1. Ingest is idempotent on `event_id` / `payload_hash`. Replays must not duplicate `EsimUsage`.
2. List dead letters: `GET /api/v1/admin/webhooks/dead-letter`.
3. Retry one event: `POST /api/v1/admin/webhooks/{id}/retry` (audited).
4. Invalid signatures stay `ignored`. Do not “force process” unsigned payloads.
5. If Redis/queue is down, events remain `pending` until workers return. Do not raise `ProcessProviderWebhookJob` tries in a way that double-applies usage.

## Failed jobs

```bash
php artisan queue:failed
php artisan queue:retry {id}
```

Purchase/fulfillment jobs use `$tries = 1` on purpose. Do not increase tries unless the job is uniquely keyed and the provider adapter is idempotent.

## Incident notes

- Correlate with `X-Request-ID`.
- Provider API log bodies are stored only when `PROVIDER_LOG_LEVEL=full` and never in production (`travora:validate-env --strict` rejects `full`).
- Audit payloads pass through `SecretMasker`. Do not dump `audit_logs.new_values` into chat.
- Rotate `storage/logs/laravel.log`. API logs older than `PROVIDER_API_LOG_RETENTION_DAYS` are deleted by `CleanupProviderApiLogsJob`. Audit rows older than `AUDIT_LOG_RETENTION_DAYS` (minimum 30) are deleted by `CleanupAuditLogsJob`.
