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
        Schema::create('esim_profiles', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('plan_id')->constrained('esim_plans')->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_profile_id')->nullable();
            $table->string('iccid', 64)->nullable()->unique();
            $table->string('matching_id')->nullable();
            $table->text('smdp_address')->nullable();
            $table->text('activation_code_encrypted')->nullable();
            $table->text('confirmation_code_encrypted')->nullable();
            $table->string('status', 30)->default('purchased');
            $table->bigInteger('data_total_mb')->nullable();
            $table->bigInteger('data_remaining_mb')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('installed_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'status']);
            $table->unique(['provider_id', 'external_profile_id']);
            $table->index(['expires_at', 'status']);
            $table->index('order_item_id');
        });

        TravoraSchema::addChecks('esim_profiles', [
            'esim_profiles_data_total_non_negative' => 'data_total_mb IS NULL OR data_total_mb >= 0',
            'esim_profiles_data_remaining_non_negative' => 'data_remaining_mb IS NULL OR data_remaining_mb >= 0',
        ]);

        Schema::create('esim_installations', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('esim_profile_id')->constrained('esim_profiles')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('user_devices')->nullOnDelete();
            $table->string('method', 30);
            $table->string('status', 30);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index(['esim_profile_id', 'created_at']);
            $table->index('device_id');
        });

        Schema::create('esim_activation_events', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('esim_profile_id')->constrained('esim_profiles')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('status', 20);
            $table->string('provider_reference')->nullable()->index();
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index(['esim_profile_id', 'created_at']);
        });

        Schema::create('esim_usage', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('esim_profile_id')->constrained('esim_profiles')->cascadeOnDelete();
            $table->timestampTz('recorded_at');
            $table->bigInteger('used_mb')->default(0);
            $table->bigInteger('remaining_mb')->nullable();
            $table->string('source', 30)->default('provider');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index(['esim_profile_id', 'recorded_at']);
            $table->index('recorded_at');
        });

        TravoraSchema::addChecks('esim_usage', [
            'esim_usage_used_mb_non_negative' => 'used_mb >= 0',
            'esim_usage_remaining_mb_non_negative' => 'remaining_mb IS NULL OR remaining_mb >= 0',
        ]);

        if (TravoraSchema::isPgsql()) {
            DB::statement('CREATE INDEX esim_usage_profile_recorded_at_desc_idx ON esim_usage (esim_profile_id, recorded_at DESC)');
        }

        Schema::create('esim_usage_daily', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('esim_profile_id')->constrained('esim_profiles')->cascadeOnDelete();
            $table->date('usage_date');
            $table->bigInteger('used_mb')->default(0);
            $table->bigInteger('remaining_mb')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->unique(['esim_profile_id', 'usage_date']);
            $table->index('usage_date');
        });

        TravoraSchema::addChecks('esim_usage_daily', [
            'esim_usage_daily_used_mb_non_negative' => 'used_mb >= 0',
            'esim_usage_daily_remaining_mb_non_negative' => 'remaining_mb IS NULL OR remaining_mb >= 0',
        ]);

        Schema::create('esim_topups', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('esim_profile_id')->constrained('esim_profiles')->restrictOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->bigInteger('data_mb')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->timestampTz('expires_at')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['esim_profile_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
            $table->index('idempotency_key');
            $table->index('order_id');
            $table->index('order_item_id');
        });

        TravoraSchema::addChecks('esim_topups', [
            'esim_topups_amount_non_negative' => 'amount_minor >= 0',
            'esim_topups_data_mb_non_negative' => 'data_mb IS NULL OR data_mb >= 0',
        ]);

        Schema::create('esim_renewals', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('esim_profile_id')->constrained('esim_profiles')->restrictOnDelete();
            $table->foreignUuid('current_plan_id')->nullable()->constrained('esim_plans')->nullOnDelete();
            $table->foreignUuid('new_plan_id')->constrained('esim_plans')->restrictOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestampTz('effective_at')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['esim_profile_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
            $table->index('idempotency_key');
            $table->index('order_id');
            $table->index('order_item_id');
        });

        TravoraSchema::addChecks('esim_renewals', [
            'esim_renewals_amount_non_negative' => 'amount_minor >= 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_renewals');
        Schema::dropIfExists('esim_topups');
        Schema::dropIfExists('esim_usage_daily');
        Schema::dropIfExists('esim_usage');
        Schema::dropIfExists('esim_activation_events');
        Schema::dropIfExists('esim_installations');
        Schema::dropIfExists('esim_profiles');
    }
};
