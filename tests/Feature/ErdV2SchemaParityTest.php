<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$erdTables = [
    'users',
    'user_profiles',
    'user_devices',
    'otp_verifications',
    'regions',
    'countries',
    'cities',
    'airports',
    'network_operators',
    'network_operator_technologies',
    'providers',
    'provider_credentials',
    'provider_services',
    'provider_country_coverage',
    'provider_health_checks',
    'provider_api_logs',
    'provider_products',
    'provider_external_references',
    'sim_products',
    'sim_inventory',
    'esim_plans',
    'esim_plan_countries',
    'esim_plan_networks',
    'esim_plan_prices',
    'orders',
    'order_items',
    'order_status_history',
    'payment_methods',
    'payments',
    'payment_attempts',
    'payment_allocations',
    'payment_refunds',
    'webhook_events',
    'wallets',
    'wallet_accounts',
    'wallet_holds',
    'wallet_transactions',
    'wallet_ledger_entries',
    'esim_profiles',
    'esim_installations',
    'esim_activation_events',
    'esim_usage',
    'esim_usage_daily',
    'esim_topups',
    'esim_renewals',
    'promotions',
    'coupon_redemptions',
    'notifications',
    'notification_preferences',
    'support_tickets',
    'support_messages',
    'support_attachments',
    'trips',
    'trip_items',
    'flight_bookings',
    'flight_segments',
    'flight_passengers',
    'hotels',
    'provider_hotels',
    'hotel_bookings',
    'car_bookings',
    'insurance_policies',
    'bus_bookings',
    'audit_logs',
];

it('migrates all 64 ERD v2 tables', function () use ($erdTables): void {
    expect($erdTables)->toHaveCount(64);

    foreach ($erdTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table.' is missing');
    }
});

it('uses uuid primary keys on ERD identity columns', function (): void {
    foreach (['users', 'orders', 'wallets', 'payments', 'esim_plans', 'providers'] as $table) {
        expect(Schema::hasColumn($table, 'id'))->toBeTrue();

        $column = collect(Schema::getColumns($table))->firstWhere('name', 'id');

        expect($column['type_name'] ?? $column['type'] ?? '')
            ->toBeIn(['uuid', 'varchar', 'character varying', 'string', 'char', 'guid']);
    }

    expect(Schema::hasColumn('user_profiles', 'user_id'))->toBeTrue();
    expect(Schema::hasColumn('network_operator_technologies', 'id'))->toBeFalse();
    expect(Schema::hasColumn('esim_plan_countries', 'id'))->toBeFalse();
});

it('enforces money, ledger, promotion, and webhook invariants', function (): void {
    $driver = Schema::getConnection()->getDriverName();

    $indexNames = collect(Schema::getIndexes('orders'))->pluck('name')->all();
    expect($indexNames)->toContain('orders_idempotency_key_unique');

    $paymentIndexes = collect(Schema::getIndexes('payments'))->pluck('name')->all();
    expect($paymentIndexes)->toContain('payments_idempotency_key_unique');

    $holdIndexes = collect(Schema::getIndexes('wallet_holds'))->pluck('name')->all();
    expect($holdIndexes)->toContain('wallet_holds_idempotency_key_unique');

    $topupIndexes = collect(Schema::getIndexes('esim_topups'))->pluck('name')->all();
    expect($topupIndexes)->toContain('esim_topups_idempotency_key_unique');

    $webhookIndexes = collect(Schema::getIndexes('webhook_events'))->pluck('name')->all();
    expect($webhookIndexes)->toContain('webhook_events_provider_id_payload_hash_unique');
    expect($webhookIndexes)->toContain('webhook_events_provider_event_id_unique');

    $priceIndexes = collect(Schema::getIndexes('esim_plan_prices'))->pluck('name')->all();
    expect($priceIndexes)->toContain('esim_plan_prices_one_active_per_currency');

    if ($driver === 'pgsql') {
        $exclusion = DB::selectOne(
            "select conname from pg_constraint where conname = 'esim_plan_prices_no_overlap'",
        );
        expect($exclusion)->not->toBeNull();
    }
});
