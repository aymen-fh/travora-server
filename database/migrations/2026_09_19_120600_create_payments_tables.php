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
        Schema::create('payment_methods', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_method_id')->nullable();
            $table->string('label', 120)->nullable();
            $table->string('last4', 4)->nullable();
            $table->string('brand', 40)->nullable();
            $table->integer('expiry_month')->nullable();
            $table->integer('expiry_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'type']);
            $table->index(['provider_id', 'provider_method_id']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->string('type', 30);
            $table->string('status', 40)->default('pending');
            $table->bigInteger('amount_minor');
            $table->bigInteger('refunded_minor')->default(0);
            $table->char('currency', 3);
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('authorized_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'created_at']);
            $table->index(['order_id', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
            $table->index(['status', 'created_at']);
        });

        TravoraSchema::addChecks('payments', [
            'payments_amounts_valid' => 'amount_minor >= 0 AND refunded_minor >= 0 AND refunded_minor <= amount_minor',
        ]);

        Schema::create('payment_attempts', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('payment_id')->constrained()->cascadeOnDelete();
            $table->integer('attempt_number');
            $table->string('status', 40);
            $table->string('provider_reference')->nullable()->index();
            $table->string('response_code', 100)->nullable();
            $table->text('response_message')->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['payment_id', 'attempt_number']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('source_type', 30);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 40)->default('pending');
            TravoraSchema::timestampsTz($table);

            $table->index('order_id');
            $table->index('payment_id');
            $table->index('wallet_transaction_id');
            $table->index(['source_type', 'status']);
        });

        TravoraSchema::addChecks('payment_allocations', [
            'payment_allocations_amount_positive' => 'amount_minor > 0',
        ]);

        Schema::create('payment_refunds', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('payment_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index('payment_id');
            $table->index(['provider_id', 'provider_reference']);
            $table->index(['status', 'created_at']);
        });

        TravoraSchema::addChecks('payment_refunds', [
            'payment_refunds_amount_positive' => 'amount_minor > 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
