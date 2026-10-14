# Travora Backend Architecture

**Tier:** T3 (complex domain + external integrations)

**Why T3:** multi-provider eSIM APIs, financial transactions, webhooks, queues, and state machines. This is not CRUD.

**Architecture skill:** `backend/laravel-clean-architecture` — minimum layers that earn their existence.

Laravel version: **13**. PHP: **8.3+**. Database: **PostgreSQL** (SQLite in-memory for automated tests).

---

## 1. Folder strategy

**Strategy A (Laravel-conventional)** with an Integrations island for provider/payment adapters.

`app/Providers/` is reserved for Laravel service providers (`AppServiceProvider`). eSIM adapters live in `app/Integrations/Esim/` so they never collide with framework bootstrapping.

```
app/
├── Actions/                  # one class = one use case (owns transactions)
│   ├── Auth/
│   ├── Orders/
│   ├── Wallet/
│   ├── Esim/
│   ├── Providers/
│   └── Support/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/Api/V1/{Admin,Webhooks}
│   ├── Middleware/
│   ├── Requests/
│   ├── Resources/
│   └── Responses/
├── Integrations/
│   ├── Esim/                 # provider abstraction (JUSTIFIED: many swap targets)
│   │   ├── Contracts/
│   │   ├── DTOs/
│   │   ├── Exceptions/
│   │   ├── Manager/
│   │   ├── Support/
│   │   └── Drivers/{Fake,EsimAccess,Template}
│   └── Payments/             # payment abstraction (JUSTIFIED: wallet + future gateways)
├── Jobs/
├── Listeners/
├── Models/
├── Notifications/
├── Policies/
├── Providers/                # Laravel service providers ONLY
├── Services/                 # shared orchestration only (pricing, catalog cache, reports)
├── States/                   # explicit status transition maps
└── Support/
```

**Earned abstractions**

| Abstraction | Why it exists |
|---|---|
| `EsimProvider` interface | Swap targets: FakeProvider and EsimAccessProvider |
| Provider DTOs | External API shapes must never leak into Travora |
| `PaymentGateway` interface | Wallet now; card/local gateways later |
| Actions | Multi-model writes, money, provider calls |
| `PricingEngine` service | Shared by checkout, admin quotes, and order history freeze |
| API Resources | Stable mobile/admin contracts |
| Jobs | Slow/external work (sync, webhooks, notifications, health) |

**Rejected**

- Repositories — Eloquent is the persistence API; no second datastore
- `if ($provider === 'x')` in Order/Wallet/Pricing
- Provider adapters under `app/Providers/`

---

## 2. Database ERD (logical)

The canonical database contract is `docs/database.md`, checked against `backend/database/migrations`.

```
users 1──* wallets 1──* wallet_transactions 1──* wallet_ledger_entries
wallets 1──* wallet_holds
users 1──* orders 1──* order_items
orders 1──* payments 1──* payment_attempts
payments 1──* payment_refunds
orders 1──* payment_allocations
providers 1──* provider_products
provider_products 1──* esim_plans 1──* esim_plan_prices
esim_plans *──* countries        (esim_plan_countries)
esim_plans *──* network_operators (esim_plan_networks)
orders 1──* esim_profiles 1──* esim_usage / esim_usage_daily
esim_profiles 1──* esim_installations / esim_activation_events
esim_profiles 1──* esim_topups / esim_renewals
providers 1──* provider_api_logs / provider_health_checks / webhook_events
users 1──* support_tickets 1──* support_messages
users 1──* audit_logs
trips 1──* trip_items
travel booking modules remain separate from core eSIM workflows
```

Travora domain tables use UUID primary keys and UUID foreign keys. Public APIs expose the same UUID identifiers. Laravel and Spatie tables keep their own key types. Do not introduce bigint internal IDs for domain rows.

Money: store monetary amounts in minor units (`*_minor` bigint) for balances, orders, payments, refunds, and ledger entries. Use decimal only for rates or percentages. Never use float.

Secrets: provider credentials are secret references and encrypted values only. Never return raw credentials in API resources or logs.

---

## 3. Migrations

Migrations under `backend/database/migrations` already create the domains below. A new table follows the same order of dependencies: identity, geography, providers, catalog, commerce, wallet, payments, eSIM lifecycle, platform, travel.

Later migrations add `user_profiles.ui_theme`, the partial unique index on system wallet accounts, and `roles.description` plus the staff permission catalog.

---

## 4. Provider abstraction

```
HTTP → Action → ProviderManager::driver($slug) → EsimProvider → Client → External API
                                              ↘ DTO mapper → Travora models
```

`ProviderManager` resolves the **driver class from config** (`config/esim.php`), not from `switch` in business code. Adding a provider = new driver folder + config entry + Admin credentials. No OrderController change.

Capabilities are checked **before** calling an operation. Unsupported → `CapabilityNotSupportedException`.

**FakeProvider** is a first-class driver for development and tests. It never calls the network.

When real API docs arrive: implement `Drivers/{Slug}/` strictly from that documentation. Do not invent endpoints.

---

## 5. API architecture

| Surface | Prefix | Auth |
|---|---|---|
| Mobile | `/api/v1` | Sanctum (customer) |
| Admin | `/api/v1/admin` | Sanctum + permission |
| Webhooks | `/api/v1/webhooks` | signature, not user tokens |

Envelope:

```json
{ "success": true, "message": "...", "data": {}, "meta": {} }
```

Lists paginate. Admin lists support `search`, filters, `sort`.

---

## 6. Authentication

Sanctum personal access tokens. Register/login issue a named device token. Users can list and revoke tokens (sessions).

Password reset, email verification, phone OTP (hashed, TTL). Login is rate-limited.

RBAC via spatie/permission follows `docs/admin_backend_specification.md` and `App\Enums\RoleName`: `user`, `support`, `operator`, `finance`, `admin`, `super_admin`. Staff admin routes also require one of `admin`, `super_admin`, `operator`, `finance`, `support`. `user` has no staff access.

---

## 7. Order lifecycle

Three columns, three state machines. Do not collapse them into one chain.

`status` (`OrderStateMachine`): `draft → pending → confirmed → processing → completed`, plus `failed` and `cancelled`. `failed` may return to `pending`.

`payment_status` (`PaymentStateMachine`): `pending → partially_paid → paid`, then `partially_refunded` or `refunded`. `failed` may return to `pending`.

`fulfillment_status` (`FulfillmentStateMachine`): `pending → processing → completed` or `partially_completed`, plus `failed` and `cancelled`. `failed` may return to `pending`.

1. Create **internal order first** (idempotency key).
2. Charge wallet **inside a DB transaction** with `lockForUpdate`.
3. **Commit** before any provider HTTP call.
4. Fulfill via Action/Job. Never blindly retry a purchase. Never fail over to another provider after a possibly-successful charge.

---

## 8. eSIM lifecycle

`EsimProfileStatus` is the only status for `esim_profiles.status`. `EsimStateMachine`:

```
purchased → provisioning → ready → installed → activated
                              ↘ suspended → activated
                              ↘ expired | cancelled
failed → provisioning | cancelled
```

Installation payload returned to clients is Travora-shaped (`smdp_address`, activation code, QR). Provider payload stays inside the adapter `metadata` and is never returned raw.

---

## 9. Wallet / payment lifecycle

Wallet debit/credit **always** writes a `wallet_transactions` row in the same transaction as the balance change.

Customer wallet top-up is **not revenue**. Revenue = product sale (`orders.total` when paid/completed). Reports keep deposits vs sales separate.

Payment gateways implement `PaymentGateway`. First driver: `WalletGateway`.

---

## 10. Provider integration lifecycle

```
Admin enables provider → Test connection → Sync products/countries
→ Catalog becomes purchasable → Purchase uses product.provider_id
→ Webhooks update eSIM/usage → Health checks every 5 minutes
```

Routing: **product → its provider**. No automatic purchase failover.

---

## 11. Pre-adapter audit (frozen)

Real adapters wait for official provider documentation. Do not invent endpoints, auth, or webhooks.

Capabilities on `EsimProvider` cover the Travora contract. Extra flags (`refund`, IMEI, provider balance, product cursors) are added only when a vendor's docs require them.

DTOs hold the Travora shape; leftover vendor fields stay in `metadata` inside the adapter and never reach mobile resources.

Timeout after a possible provider-side create must **not** mark the order failed. `status` and `fulfillment_status` stay `processing`. `SweepStaleFulfillmentsJob` dispatches reconcile by the stored idempotency key. A clear provider error may fail. Refund after an exhausted reconcile is explicit — never a second purchase, never failover. The eSIMAccess operation list is `docs/ESIMACCESS_REST_CONTRACT.md`. Renewal is not a documented partner operation.
