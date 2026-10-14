<?php

declare(strict_types=1);

use App\Support\Database\TravoraSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_products', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_product_id')->nullable()->constrained('provider_products')->nullOnDelete();
            $table->foreignUuid('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->bigInteger('data_mb')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->integer('validity_days')->nullable();
            $table->bigInteger('price_minor');
            $table->char('currency', 3)->default('LYD');
            $table->string('status', 20)->default('active');
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['provider_id', 'provider_product_id']);
            $table->index(['country_id', 'type', 'status']);
        });

        TravoraSchema::addChecks('sim_products', [
            'sim_products_price_minor_non_negative' => 'price_minor >= 0',
            'sim_products_data_mb_non_negative' => 'data_mb IS NULL OR data_mb >= 0',
        ]);

        Schema::create('esim_plans', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_product_id')->nullable()->constrained('provider_products')->nullOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->bigInteger('data_mb')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->integer('validity_days');
            $table->string('speed_type', 30)->nullable();
            $table->boolean('hotspot_supported')->default(false);
            $table->text('activation_policy')->nullable();
            $table->text('fair_usage_policy')->nullable();
            $table->boolean('auto_topup_supported')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('active');
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['provider_id', 'provider_product_id']);
            $table->index(['status', 'is_featured']);
        });

        TravoraSchema::addChecks('esim_plans', [
            'esim_plans_data_required_unless_unlimited' => 'is_unlimited OR data_mb IS NOT NULL',
            'esim_plans_data_mb_non_negative' => 'data_mb IS NULL OR data_mb >= 0',
        ]);

        Schema::create('esim_plan_countries', function (Blueprint $table): void {
            $table->foreignUuid('esim_plan_id')->constrained('esim_plans')->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained()->restrictOnDelete();
            $table->primary(['esim_plan_id', 'country_id']);
            $table->index('country_id');
        });

        Schema::create('esim_plan_networks', function (Blueprint $table): void {
            $table->foreignUuid('esim_plan_id')->constrained('esim_plans')->cascadeOnDelete();
            $table->foreignUuid('network_operator_id')->constrained('network_operators')->restrictOnDelete();
            $table->primary(['esim_plan_id', 'network_operator_id']);
            $table->index('network_operator_id');
        });

        Schema::create('esim_plan_prices', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('esim_plan_id')->constrained('esim_plans')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('price_minor');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at');

            $table->index(['esim_plan_id', 'currency', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });

        TravoraSchema::addChecks('esim_plan_prices', [
            'esim_plan_prices_price_minor_non_negative' => 'price_minor >= 0',
        ]);

        TravoraSchema::uniqueWhere(
            'esim_plan_prices_one_active_per_currency',
            'esim_plan_prices',
            ['esim_plan_id', 'currency'],
            'is_active = true',
            'is_active = 1',
        );

        if (TravoraSchema::isPgsql()) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement(<<<'SQL'
                ALTER TABLE esim_plan_prices
                ADD CONSTRAINT esim_plan_prices_no_overlap
                EXCLUDE USING gist (
                    esim_plan_id WITH =,
                    currency WITH =,
                    tstzrange(COALESCE(starts_at, '-infinity'), COALESCE(ends_at, 'infinity'), '[)') WITH &&
                )
                WHERE (is_active)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_plan_prices');
        Schema::dropIfExists('esim_plan_networks');
        Schema::dropIfExists('esim_plan_countries');
        Schema::dropIfExists('esim_plans');
        Schema::dropIfExists('sim_products');
    }
};
