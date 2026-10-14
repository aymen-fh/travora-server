<?php

declare(strict_types=1);

namespace App\Domain\References;

use App\Enums\OrderItemType;
use App\Enums\TripItemType;
use App\Models\BusBooking;
use App\Models\CarBooking;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\EsimTopup;
use App\Models\FlightBooking;
use App\Models\HotelBooking;
use App\Models\InsurancePolicy;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SimProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class AssertReference
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const ORDER_ITEMS = [
        'esim_plan' => EsimPlan::class,
        'esim_topup' => EsimTopup::class,
        'sim_product' => SimProduct::class,
        'flight_booking' => FlightBooking::class,
        'hotel_booking' => HotelBooking::class,
        'car_booking' => CarBooking::class,
        'insurance_policy' => InsurancePolicy::class,
        'bus_booking' => BusBooking::class,
    ];

    /**
     * @var array<string, class-string<Model>>
     */
    private const TRIP_ITEMS = [
        'esim_profile' => EsimProfile::class,
        'flight_booking' => FlightBooking::class,
        'hotel_booking' => HotelBooking::class,
        'car_booking' => CarBooking::class,
        'insurance_policy' => InsurancePolicy::class,
        'bus_booking' => BusBooking::class,
    ];

    /**
     * @var array<string, class-string<Model>>
     */
    private const WALLET_REFERENCES = [
        Order::class => Order::class,
        Payment::class => Payment::class,
        User::class => User::class,
    ];

    public function orderItem(OrderItemType|string $itemType, ?string $referenceId): void
    {
        $type = $itemType instanceof OrderItemType ? $itemType : OrderItemType::from($itemType);
        $this->requireRow(self::ORDER_ITEMS[$type->value], $referenceId, 'order item');
    }

    public function wallet(?string $referenceType, ?string $referenceId): void
    {
        if ($referenceType === null && $referenceId === null) {
            return;
        }

        if ($referenceType === null || $referenceId === null || ! isset(self::WALLET_REFERENCES[$referenceType])) {
            throw new InvalidArgumentException('Wallet reference type is not allowed.');
        }

        $this->requireRow(self::WALLET_REFERENCES[$referenceType], $referenceId, 'wallet');
    }

    public function tripItem(TripItemType|string $itemType, ?string $referenceId): void
    {
        $type = $itemType instanceof TripItemType ? $itemType : TripItemType::from($itemType);

        if (in_array($type, [TripItemType::Activity, TripItemType::Note], true)) {
            return;
        }

        $this->requireRow(self::TRIP_ITEMS[$type->value], $referenceId, 'trip item');
    }

    public function audit(?string $entityType, ?string $entityId): void
    {
        if ($entityType === null || $entityId === null) {
            return;
        }

        if (! class_exists($entityType) || ! is_subclass_of($entityType, Model::class)) {
            return;
        }

        $this->requireRow($entityType, $entityId, 'audit');
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function requireRow(string $model, ?string $referenceId, string $context): void
    {
        if ($referenceId === null || $referenceId === '' || $model::query()->whereKey($referenceId)->doesntExist()) {
            throw new InvalidArgumentException('Referenced '.$context.' row does not exist.');
        }
    }
}
