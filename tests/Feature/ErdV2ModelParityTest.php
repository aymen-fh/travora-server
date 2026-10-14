<?php

declare(strict_types=1);

use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\RecordStatus;
use App\Enums\WalletStatus;
use App\Models\Country;
use App\Models\EsimPlan;
use App\Models\EsimPlanCountry;
use App\Models\EsimPlanNetwork;
use App\Models\EsimProfile;
use App\Models\NetworkOperatorTechnology;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderCredential;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\Relation;

$erdModels = [
    'users' => User::class,
    'user_profiles' => App\Models\UserProfile::class,
    'user_devices' => App\Models\UserDevice::class,
    'otp_verifications' => App\Models\OtpVerification::class,
    'regions' => App\Models\Region::class,
    'countries' => Country::class,
    'cities' => App\Models\City::class,
    'airports' => App\Models\Airport::class,
    'network_operators' => App\Models\NetworkOperator::class,
    'network_operator_technologies' => NetworkOperatorTechnology::class,
    'providers' => Provider::class,
    'provider_credentials' => ProviderCredential::class,
    'provider_services' => App\Models\ProviderService::class,
    'provider_country_coverage' => App\Models\ProviderCountryCoverage::class,
    'provider_health_checks' => App\Models\ProviderHealthCheck::class,
    'provider_api_logs' => App\Models\ProviderApiLog::class,
    'provider_products' => App\Models\ProviderProduct::class,
    'provider_external_references' => App\Models\ProviderExternalReference::class,
    'sim_products' => App\Models\SimProduct::class,
    'sim_inventory' => App\Models\SimInventory::class,
    'esim_plans' => EsimPlan::class,
    'esim_plan_countries' => EsimPlanCountry::class,
    'esim_plan_networks' => EsimPlanNetwork::class,
    'esim_plan_prices' => App\Models\EsimPlanPrice::class,
    'orders' => Order::class,
    'order_items' => App\Models\OrderItem::class,
    'order_status_history' => App\Models\OrderStatusHistory::class,
    'payment_methods' => App\Models\PaymentMethod::class,
    'payments' => App\Models\Payment::class,
    'payment_attempts' => App\Models\PaymentAttempt::class,
    'payment_allocations' => App\Models\PaymentAllocation::class,
    'payment_refunds' => App\Models\PaymentRefund::class,
    'webhook_events' => App\Models\WebhookEvent::class,
    'wallets' => Wallet::class,
    'wallet_accounts' => App\Models\WalletAccount::class,
    'wallet_holds' => App\Models\WalletHold::class,
    'wallet_transactions' => App\Models\WalletTransaction::class,
    'wallet_ledger_entries' => App\Models\WalletLedgerEntry::class,
    'esim_profiles' => EsimProfile::class,
    'esim_installations' => App\Models\EsimInstallation::class,
    'esim_activation_events' => App\Models\EsimActivationEvent::class,
    'esim_usage' => App\Models\EsimUsage::class,
    'esim_usage_daily' => App\Models\EsimUsageDaily::class,
    'esim_topups' => App\Models\EsimTopup::class,
    'esim_renewals' => App\Models\EsimRenewal::class,
    'promotions' => App\Models\Promotion::class,
    'coupon_redemptions' => App\Models\CouponRedemption::class,
    'notifications' => App\Models\Notification::class,
    'notification_preferences' => App\Models\NotificationPreference::class,
    'support_tickets' => App\Models\SupportTicket::class,
    'support_messages' => App\Models\SupportMessage::class,
    'support_attachments' => App\Models\SupportAttachment::class,
    'trips' => App\Models\Trip::class,
    'trip_items' => App\Models\TripItem::class,
    'flight_bookings' => App\Models\FlightBooking::class,
    'flight_segments' => App\Models\FlightSegment::class,
    'flight_passengers' => App\Models\FlightPassenger::class,
    'hotels' => App\Models\Hotel::class,
    'provider_hotels' => App\Models\ProviderHotel::class,
    'hotel_bookings' => App\Models\HotelBooking::class,
    'car_bookings' => App\Models\CarBooking::class,
    'insurance_policies' => App\Models\InsurancePolicy::class,
    'bus_bookings' => App\Models\BusBooking::class,
    'audit_logs' => App\Models\AuditLog::class,
];

it('maps each of the 64 ERD tables to an Eloquent model', function () use ($erdModels): void {
    expect($erdModels)->toHaveCount(64);

    foreach ($erdModels as $table => $model) {
        expect((new $model)->getTable())->toBe($table);
    }
});

it('uses uuid primary keys except for documented composite and owned-key tables', function () use ($erdModels): void {
    $composite = [
        NetworkOperatorTechnology::class,
        EsimPlanCountry::class,
        EsimPlanNetwork::class,
    ];

    $ownedKeys = [
        App\Models\UserProfile::class,
        App\Models\NotificationPreference::class,
    ];

    foreach ($erdModels as $model) {
        $instance = new $model;

        if (in_array($model, $composite, true)) {
            expect($instance->getIncrementing())->toBeFalse();
            expect($instance->getKeyName())->toBeArray();

            continue;
        }

        expect($instance->getIncrementing())->toBeFalse();
        expect($instance->getKeyType())->toBe('string');

        if (in_array($model, $ownedKeys, true)) {
            expect($instance->getKeyName())->toBe('user_id');

            continue;
        }

        expect($instance->getKeyName())->toBe('id');
    }
});

it('casts order money as integers and statuses as ERD enums', function (): void {
    $order = Order::factory()->create();
    $order->load('user');

    expect($order->id)->toBeString()
        ->and($order->status)->toBeInstanceOf(OrderStatus::class)
        ->and($order->payment_status)->toBeInstanceOf(OrderPaymentStatus::class)
        ->and($order->fulfillment_status)->toBeInstanceOf(FulfillmentStatus::class)
        ->and($order->total_minor)->toBeInt()
        ->and($order->user)->toBeInstanceOf(User::class);
});

it('connects identity, catalog, and wallet aggregates through explicit relations', function (): void {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $plan = EsimPlan::factory()->create();
    $country = Country::factory()->create();

    $plan->countries()->attach($country->id);

    $user->load(['wallets', 'orders']);
    $wallet->load('user');
    $plan->load(['provider', 'countries']);

    expect($wallet->user->is($user))->toBeTrue()
        ->and($user->wallets)->toHaveCount(1)
        ->and($plan->provider)->toBeInstanceOf(Provider::class)
        ->and($plan->provider->status)->toBeInstanceOf(RecordStatus::class)
        ->and($plan->countries->first()->is($country))->toBeTrue()
        ->and($wallet->status)->toBe(WalletStatus::Active)
        ->and($wallet->available_balance_minor)->toBeInt();
});

it('hides encrypted credentials from serialization', function (): void {
    $plan = EsimPlan::factory()->create();
    $credential = new ProviderCredential;
    $credential->provider_id = $plan->provider_id;
    $credential->environment = 'sandbox';
    $credential->credential_key = 'api_token';
    $credential->secret_reference = 'vault://esim/token';
    $credential->save();

    $profile = new EsimProfile;
    $profile->user_id = User::factory()->create()->id;
    $profile->plan_id = $plan->id;
    $profile->provider_id = $plan->provider_id;
    $profile->status = EsimProfileStatus::Purchased;
    $profile->activation_code_encrypted = 'LPA:1$secret';
    $profile->confirmation_code_encrypted = '1234';
    $profile->save();

    expect($credential->toArray())->not->toHaveKey('secret_reference')
        ->and($profile->toArray())->not->toHaveKey('activation_code_encrypted')
        ->and($profile->toArray())->not->toHaveKey('confirmation_code_encrypted')
        ->and($credential->secret_reference)->toBe('vault://esim/token')
        ->and($profile->activation_code_encrypted)->toBe('LPA:1$secret');
});

it('rejects mass assignment of ownership fields', function (): void {
    $user = User::factory()->create();

    expect(fn () => Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'TRV-MASS',
        'currency' => 'LYD',
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'tax_minor' => 0,
        'total_minor' => 0,
        'paid_minor' => 0,
    ]))->toThrow(MassAssignmentException::class);

    expect((new User)->getHidden())->toContain('password_hash');
});

it('exposes typed relationship methods instead of morph maps for ERD foreign keys', function (): void {
    $order = new Order;

    expect($order->user())->toBeInstanceOf(Relation::class)
        ->and($order->items())->toBeInstanceOf(Relation::class)
        ->and($order->payments())->toBeInstanceOf(Relation::class)
        ->and((new EsimPlan)->countries())->toBeInstanceOf(Relation::class)
        ->and((new EsimPlan)->networks())->toBeInstanceOf(Relation::class);
});
