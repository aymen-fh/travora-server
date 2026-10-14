<?php

declare(strict_types=1);

use App\Enums\EsimProfileStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WebhookEventStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\States\EsimStateMachine;
use App\States\FulfillmentStateMachine;
use App\States\OrderStateMachine;
use App\States\PaymentStateMachine;
use App\States\WalletHoldStateMachine;
use App\States\WebhookEventStateMachine;

it('allows pending orders to become confirmed or cancelled', function (): void {
    expect(OrderStateMachine::can(OrderStatus::Pending, OrderStatus::Confirmed))->toBeTrue();
    expect(OrderStateMachine::can(OrderStatus::Pending, OrderStatus::Cancelled))->toBeTrue();
    expect(OrderStateMachine::can(OrderStatus::Completed, OrderStatus::Cancelled))->toBeFalse();
});

it('blocks illegal payment transitions', function (): void {
    PaymentStateMachine::assert(OrderPaymentStatus::Paid, OrderPaymentStatus::Pending);
})->throws(InvalidStateTransitionException::class);

it('allows a captured hold only from active', function (): void {
    expect(WalletHoldStateMachine::can(WalletHoldStatus::Active, WalletHoldStatus::Captured))->toBeTrue();
    expect(WalletHoldStateMachine::can(WalletHoldStatus::Captured, WalletHoldStatus::Released))->toBeFalse();
});

it('requires provisioning before an eSIM becomes ready', function (): void {
    expect(EsimStateMachine::can(EsimProfileStatus::Purchased, EsimProfileStatus::Ready))->toBeFalse();
    expect(EsimStateMachine::can(EsimProfileStatus::Purchased, EsimProfileStatus::Provisioning))->toBeTrue();
    expect(EsimStateMachine::can(EsimProfileStatus::Provisioning, EsimProfileStatus::Ready))->toBeTrue();
});

it('allows fulfillment processing to complete or fail', function (): void {
    expect(FulfillmentStateMachine::can(FulfillmentStatus::Processing, FulfillmentStatus::Completed))->toBeTrue();
    expect(FulfillmentStateMachine::can(FulfillmentStatus::Pending, FulfillmentStatus::Completed))->toBeFalse();
});

it('treats processed webhook events as terminal', function (): void {
    expect(WebhookEventStateMachine::can(WebhookEventStatus::Processed, WebhookEventStatus::Pending))->toBeFalse();
    expect(WebhookEventStateMachine::can(WebhookEventStatus::Failed, WebhookEventStatus::Pending))->toBeTrue();
});
