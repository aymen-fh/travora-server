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
        Schema::create('promotions', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->string('name', 150);
            $table->string('code', 80)->nullable()->unique();
            $table->string('type', 30);
            $table->bigInteger('value_minor')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('per_user_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('rules')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index('code');
        });

        TravoraSchema::addChecks('promotions', [
            'promotions_usage_count_non_negative' => 'usage_count >= 0',
            'promotions_value_minor_non_negative' => 'value_minor IS NULL OR value_minor >= 0',
            'promotions_fixed_amount_requires_value' => "type <> 'fixed_amount' OR value_minor IS NOT NULL",
            'promotions_percentage_range' => "type <> 'percentage' OR (percentage IS NOT NULL AND percentage >= 0 AND percentage <= 100)",
        ]);

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('promotion_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->bigInteger('discount_minor');
            $table->timestampTz('redeemed_at');

            $table->unique(['promotion_id', 'user_id', 'order_id']);
            $table->index(['user_id', 'redeemed_at']);
            $table->index('order_id');
        });

        TravoraSchema::addChecks('coupon_redemptions', [
            'coupon_redemptions_discount_non_negative' => 'discount_minor >= 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('promotions');
    }
};
