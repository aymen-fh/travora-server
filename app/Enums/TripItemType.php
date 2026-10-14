<?php

declare(strict_types=1);

namespace App\Enums;

enum TripItemType: string
{
    case EsimProfile = 'esim_profile';
    case FlightBooking = 'flight_booking';
    case HotelBooking = 'hotel_booking';
    case CarBooking = 'car_booking';
    case InsurancePolicy = 'insurance_policy';
    case BusBooking = 'bus_booking';
    case Activity = 'activity';
    case Note = 'note';
}
