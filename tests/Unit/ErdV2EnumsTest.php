<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\EsimOperationStatus;
use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\HealthStatus;
use App\Enums\OrderItemType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ProviderType;
use App\Enums\RecordStatus;
use App\Enums\RefundStatus;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\TripItemType;
use App\Enums\TripStatus;
use App\Enums\UserStatus;
use App\Enums\WalletDirection;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WebhookEventStatus;

$erdEnums = [
    UserStatus::class => ['active', 'suspended', 'closed'],
    ProviderType::class => ['esim', 'sim', 'flight', 'hotel', 'car', 'insurance', 'bus', 'payment'],
    RecordStatus::class => ['active', 'inactive', 'archived'],
    HealthStatus::class => ['unknown', 'healthy', 'degraded', 'down'],
    OrderStatus::class => ['draft', 'pending', 'confirmed', 'processing', 'completed', 'cancelled', 'failed'],
    OrderPaymentStatus::class => ['pending', 'partially_paid', 'paid', 'failed', 'refunded', 'partially_refunded'],
    FulfillmentStatus::class => ['pending', 'processing', 'completed', 'partially_completed', 'failed', 'cancelled'],
    OrderItemType::class => ['esim_plan', 'esim_topup', 'sim_product', 'flight_booking', 'hotel_booking', 'car_booking', 'insurance_policy', 'bus_booking'],
    TripItemType::class => ['esim_profile', 'flight_booking', 'hotel_booking', 'car_booking', 'insurance_policy', 'bus_booking', 'activity', 'note'],
    PaymentType::class => ['charge', 'refund', 'wallet_topup'],
    PaymentStatus::class => ['pending', 'authorized', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'],
    RefundStatus::class => ['pending', 'processing', 'completed', 'failed', 'cancelled'],
    WalletStatus::class => ['active', 'locked', 'closed'],
    WalletTransactionStatus::class => ['pending', 'posted', 'reversed', 'failed'],
    WalletHoldStatus::class => ['active', 'captured', 'released', 'expired'],
    WalletDirection::class => ['credit', 'debit'],
    EsimOperationStatus::class => ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'],
    EsimProfileStatus::class => ['purchased', 'provisioning', 'ready', 'installed', 'activated', 'suspended', 'expired', 'cancelled', 'failed'],
    BookingStatus::class => ['draft', 'pending', 'confirmed', 'ticketed', 'checked_in', 'completed', 'cancelled', 'failed', 'refunded'],
    WebhookEventStatus::class => ['pending', 'processing', 'processed', 'ignored', 'failed'],
    SupportTicketStatus::class => ['open', 'pending_customer', 'pending_support', 'resolved', 'closed'],
    SupportPriority::class => ['low', 'normal', 'high', 'urgent'],
    TripStatus::class => ['planned', 'active', 'completed', 'cancelled'],
];

it('implements all 23 ERD v2 enums with matching cases', function () use ($erdEnums): void {
    expect($erdEnums)->toHaveCount(23);

    foreach ($erdEnums as $enum => $values) {
        $cases = array_map(static fn (BackedEnum $case): string => $case->value, $enum::cases());

        expect($cases)->toBe($values, $enum.' cases do not match ERD v2');
    }
});
