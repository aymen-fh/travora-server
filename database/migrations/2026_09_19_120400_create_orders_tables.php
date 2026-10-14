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
        Schema::create('orders', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('status', 40)->default('pending');
            $table->string('payment_status', 40)->default('pending');
            $table->string('fulfillment_status', 40)->default('pending');
            $table->char('currency', 3)->default('LYD');
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->string('customer_email_snapshot')->nullable();
            $table->string('customer_phone_snapshot', 30)->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('placed_at')->nullable()->index();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index(['fulfillment_status', 'created_at']);
        });

        TravoraSchema::addChecks('orders', [
            'orders_money_non_negative' => 'subtotal_minor >= 0 AND discount_minor >= 0 AND tax_minor >= 0 AND total_minor >= 0 AND paid_minor >= 0',
            'orders_total_identity' => 'total_minor = subtotal_minor - discount_minor + tax_minor',
        ]);

        Schema::create('order_items', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 40);
            $table->string('reference_type', 80)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->foreignUuid('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot');
            $table->text('description_snapshot')->nullable();
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index('order_id');
            $table->index('provider_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['item_type', 'reference_id']);
        });

        TravoraSchema::addChecks('order_items', [
            'order_items_quantity_positive' => 'quantity > 0',
            'order_items_money_non_negative' => 'unit_price_minor >= 0 AND discount_minor >= 0 AND tax_minor >= 0 AND total_minor >= 0',
        ]);

        Schema::create('order_status_history', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('state_type', 30);
            $table->string('from_value', 40)->nullable();
            $table->string('to_value', 40);
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->index(['order_id', 'created_at']);
            $table->index(['order_id', 'state_type', 'created_at']);
            $table->index('created_by');
        });

        Schema::create('sim_inventory', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('sim_product_id')->constrained('sim_products')->restrictOnDelete();
            $table->string('external_inventory_id')->nullable();
            $table->string('iccid', 64)->nullable()->unique();
            $table->string('imsi', 64)->nullable()->index();
            $table->string('msisdn', 40)->nullable()->index();
            $table->string('status', 30)->default('available');
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->jsonb('metadata')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['sim_product_id', 'status']);
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_inventory');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
