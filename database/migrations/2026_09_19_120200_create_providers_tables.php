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
        Schema::create('providers', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->string('type', 40);
            $table->string('name', 150);
            $table->string('code', 80)->unique();
            $table->string('status', 20)->default('active');
            $table->integer('priority')->default(100);
            $table->string('health_status', 20)->default('unknown')->index();
            $table->timestampTz('health_checked_at')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['type', 'status', 'priority']);
        });

        Schema::create('provider_credentials', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20);
            $table->string('credential_key', 100);
            $table->text('secret_reference');
            TravoraSchema::timestampsTz($table);

            $table->unique(['provider_id', 'environment', 'credential_key']);
        });

        Schema::create('provider_services', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 40);
            $table->boolean('is_enabled')->default(true);
            $table->integer('priority')->default(100);
            $table->jsonb('config')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->unique(['provider_id', 'service_type']);
            $table->index(['service_type', 'is_enabled', 'priority']);
        });

        Schema::create('provider_country_coverage', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained()->restrictOnDelete();
            $table->string('service_type', 40);
            $table->string('status', 20)->default('active');
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->unique(['provider_id', 'country_id', 'service_type']);
            $table->index(['country_id', 'service_type', 'status']);
        });

        Schema::create('provider_health_checks', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 40)->nullable();
            $table->string('status', 20)->index();
            $table->integer('latency_ms')->nullable();
            $table->text('message')->nullable();
            $table->timestampTz('checked_at');
            $table->jsonb('metadata')->nullable();

            $table->index(['provider_id', 'checked_at']);
            $table->index(['provider_id', 'service_type', 'checked_at']);
        });

        Schema::create('provider_api_logs', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 40)->nullable();
            $table->string('direction', 20);
            $table->string('endpoint')->nullable();
            $table->string('method', 10)->nullable();
            $table->integer('status_code')->nullable()->index();
            $table->integer('duration_ms')->nullable();
            $table->string('correlation_id', 120)->nullable()->index();
            $table->jsonb('request_body')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at');

            $table->index(['provider_id', 'created_at']);
        });

        Schema::create('provider_products', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('product_type', 40);
            $table->string('external_product_id');
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->jsonb('raw_data')->nullable();
            $table->timestampTz('synced_at')->nullable()->index();
            TravoraSchema::timestampsTz($table);

            $table->unique(['provider_id', 'product_type', 'external_product_id']);
            $table->index(['product_type', 'status']);
        });

        Schema::create('provider_external_references', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50);
            $table->uuid('entity_id');
            $table->string('external_id');
            $table->string('external_type', 100)->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->unique(['provider_id', 'entity_type', 'external_id']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['provider_id', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_external_references');
        Schema::dropIfExists('provider_products');
        Schema::dropIfExists('provider_api_logs');
        Schema::dropIfExists('provider_health_checks');
        Schema::dropIfExists('provider_country_coverage');
        Schema::dropIfExists('provider_services');
        Schema::dropIfExists('provider_credentials');
        Schema::dropIfExists('providers');
    }
};
