<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderItemType: string
{
    case EsimPlan = 'esim_plan';
    case EsimTopup = 'esim_topup';
    case SimProduct = 'sim_product';
    case FlightBooking = 'flight_booking';
    case HotelBooking = 'hotel_booking';
    case CarBooking = 'car_booking';
    case InsurancePolicy = 'insurance_policy';
    case BusBooking = 'bus_booking';
}
