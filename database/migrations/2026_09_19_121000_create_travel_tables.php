<?php

declare(strict_types=1);

use App\Support\Database\TravoraSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->foreignUuid('destination_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->timestampTz('start_at')->nullable();
            $table->timestampTz('end_at')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'start_at']);
            $table->index(['destination_country_id', 'start_at']);
        });

        Schema::create('trip_items', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('trip_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 40);
            $table->uuid('reference_id')->nullable();
            $table->string('title');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('location_text')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['trip_id', 'starts_at']);
            $table->index(['item_type', 'reference_id']);
        });

        Schema::create('flight_bookings', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('trip_item_id')->nullable()->constrained('trip_items')->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booking_reference', 80)->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->string('airline_code', 10)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->bigInteger('total_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->integer('passenger_count')->default(1);
            $table->timestampTz('booked_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
        });

        TravoraSchema::addChecks('flight_bookings', [
            'flight_bookings_amount_non_negative' => 'total_amount_minor IS NULL OR total_amount_minor >= 0',
            'flight_bookings_passenger_count_positive' => 'passenger_count > 0',
        ]);

        Schema::create('flight_segments', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('flight_booking_id')->constrained('flight_bookings')->cascadeOnDelete();
            $table->foreignUuid('origin_airport_id')->nullable()->constrained('airports')->nullOnDelete();
            $table->foreignUuid('destination_airport_id')->nullable()->constrained('airports')->nullOnDelete();
            $table->string('airline_code', 10)->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->string('cabin_class', 30)->nullable();
            $table->timestampTz('departure_at');
            $table->timestampTz('arrival_at');
            $table->integer('sequence');
            $table->timestampTz('created_at');

            $table->unique(['flight_booking_id', 'sequence']);
            $table->index(['origin_airport_id', 'departure_at']);
            $table->index('destination_airport_id');
        });

        Schema::create('flight_passengers', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('flight_booking_id')->constrained('flight_bookings')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('passenger_type', 20);
            $table->date('date_of_birth')->nullable();
            $table->string('document_type', 30)->nullable();
            $table->text('document_number_encrypted')->nullable();
            $table->foreignUuid('nationality_country_id')->nullable()->constrained('countries')->nullOnDelete();
            TravoraSchema::timestampsTz($table);

            $table->index('flight_booking_id');
        });

        Schema::create('hotels', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->decimal('star_rating', 2, 1)->nullable();
            $table->jsonb('amenities')->nullable();
            $table->string('status', 20)->default('active');
            TravoraSchema::timestampsTz($table);

            $table->index(['city_id', 'status']);
            $table->index(['country_id', 'status']);
        });

        Schema::create('provider_hotels', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('external_hotel_id');
            $table->string('external_name')->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->timestampTz('synced_at')->nullable()->index();
            TravoraSchema::timestampsTz($table);

            $table->unique(['provider_id', 'external_hotel_id']);
            $table->unique(['hotel_id', 'provider_id']);
        });

        Schema::create('hotel_bookings', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('trip_item_id')->nullable()->constrained('trip_items')->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booking_reference', 80)->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('guest_name_snapshot')->nullable();
            $table->string('room_name_snapshot')->nullable();
            $table->integer('room_count')->default(1);
            $table->integer('guest_count')->default(1);
            $table->timestampTz('check_in_at');
            $table->timestampTz('check_out_at');
            $table->bigInteger('total_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
            $table->index('hotel_id');
        });

        TravoraSchema::addChecks('hotel_bookings', [
            'hotel_bookings_amount_non_negative' => 'total_amount_minor IS NULL OR total_amount_minor >= 0',
            'hotel_bookings_counts_positive' => 'room_count > 0 AND guest_count > 0',
        ]);

        Schema::create('car_bookings', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('trip_item_id')->nullable()->constrained('trip_items')->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booking_reference', 80)->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignUuid('pickup_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignUuid('dropoff_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('pickup_location')->nullable();
            $table->string('dropoff_location')->nullable();
            $table->timestampTz('pickup_at');
            $table->timestampTz('dropoff_at');
            $table->string('vehicle_class', 80)->nullable();
            $table->integer('driver_age')->nullable();
            $table->bigInteger('total_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
            $table->index('pickup_city_id');
        });

        TravoraSchema::addChecks('car_bookings', [
            'car_bookings_amount_non_negative' => 'total_amount_minor IS NULL OR total_amount_minor >= 0',
        ]);

        Schema::create('insurance_policies', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('trip_item_id')->nullable()->constrained('trip_items')->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('policy_number', 80)->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('coverage_type', 80)->nullable();
            $table->foreignUuid('destination_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('insured_name_snapshot')->nullable();
            $table->bigInteger('total_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('policy_document_url')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
        });

        TravoraSchema::addChecks('insurance_policies', [
            'insurance_policies_amount_non_negative' => 'total_amount_minor IS NULL OR total_amount_minor >= 0',
        ]);

        Schema::create('bus_bookings', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('trip_item_id')->nullable()->constrained('trip_items')->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booking_reference', 80)->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignUuid('origin_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignUuid('destination_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('carrier_name', 150)->nullable();
            $table->string('seat_number', 20)->nullable();
            $table->timestampTz('departure_at');
            $table->timestampTz('arrival_at')->nullable();
            $table->string('passenger_name_snapshot')->nullable();
            $table->bigInteger('total_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
            $table->index(['origin_city_id', 'departure_at']);
            $table->index('destination_city_id');
        });

        TravoraSchema::addChecks('bus_bookings', [
            'bus_bookings_amount_non_negative' => 'total_amount_minor IS NULL OR total_amount_minor >= 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_bookings');
        Schema::dropIfExists('insurance_policies');
        Schema::dropIfExists('car_bookings');
        Schema::dropIfExists('hotel_bookings');
        Schema::dropIfExists('provider_hotels');
        Schema::dropIfExists('hotels');
        Schema::dropIfExists('flight_passengers');
        Schema::dropIfExists('flight_segments');
        Schema::dropIfExists('flight_bookings');
        Schema::dropIfExists('trip_items');
        Schema::dropIfExists('trips');
    }
};
