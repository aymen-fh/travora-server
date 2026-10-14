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
        Schema::create('regions', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->string('name', 120);
            $table->string('code', 20)->nullable()->unique();
            TravoraSchema::timestampsTz($table);
        });

        Schema::create('countries', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120)->index();
            $table->string('name_ar', 120)->nullable();
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable()->unique();
            $table->string('calling_code', 10)->nullable();
            $table->text('flag_url')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true);
            TravoraSchema::timestampsTz($table);

            $table->index(['region_id', 'is_active']);
        });

        Schema::create('cities', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('country_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('name_ar', 120)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true);
            TravoraSchema::timestampsTz($table);

            $table->index('country_id');
            $table->index(['country_id', 'name']);
            $table->index(['country_id', 'is_active']);
        });

        Schema::create('airports', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('country_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->char('iata_code', 3)->nullable()->unique();
            $table->char('icao_code', 4)->nullable()->unique();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true);
            TravoraSchema::timestampsTz($table);

            $table->index(['country_id', 'is_active']);
            $table->index('city_id');
        });

        Schema::create('network_operators', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('country_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('mcc', 3)->nullable();
            $table->string('mnc', 3)->nullable();
            $table->boolean('is_active')->default(true);
            TravoraSchema::timestampsTz($table);

            $table->index(['country_id', 'name']);
            $table->unique(['country_id', 'mcc', 'mnc']);
            $table->index(['country_id', 'is_active']);
        });

        Schema::create('network_operator_technologies', function (Blueprint $table): void {
            $table->foreignUuid('network_operator_id')->constrained('network_operators')->cascadeOnDelete();
            $table->string('technology', 20);
            $table->primary(['network_operator_id', 'technology']);
            $table->index('technology');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_operator_technologies');
        Schema::dropIfExists('network_operators');
        Schema::dropIfExists('airports');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('regions');
    }
};
