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
        Schema::create('wallets', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3)->default('LYD');
            $table->bigInteger('available_balance_minor')->default(0);
            $table->bigInteger('held_balance_minor')->default(0);
            $table->string('status', 20)->default('active')->index();
            TravoraSchema::timestampsTz($table);

            $table->unique(['user_id', 'currency']);
        });

        TravoraSchema::addChecks('wallets', [
            'wallets_balances_non_negative' => 'available_balance_minor >= 0 AND held_balance_minor >= 0',
        ]);

        Schema::create('wallet_accounts', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 80);
            $table->string('name', 150);
            $table->char('currency', 3);
            $table->boolean('is_system')->default(false);
            TravoraSchema::timestampsTz($table);

            $table->unique(['wallet_id', 'code', 'currency']);
            $table->index(['code', 'currency']);
        });

        Schema::create('wallet_holds', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('wallet_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('active');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('captured_at')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['wallet_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });

        TravoraSchema::addChecks('wallet_holds', [
            'wallet_holds_amount_positive' => 'amount_minor > 0',
        ]);

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('wallet_hold_id')->nullable()->constrained('wallet_holds')->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 20)->default('posted');
            $table->string('direction', 20);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_before_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('reference_type', 40)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index(['wallet_id', 'created_at']);
            $table->index('wallet_hold_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['wallet_id', 'status']);
        });

        TravoraSchema::addChecks('wallet_transactions', [
            'wallet_transactions_amount_positive' => 'amount_minor > 0',
        ]);

        Schema::create('wallet_ledger_entries', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('wallet_transaction_id')->constrained('wallet_transactions')->cascadeOnDelete();
            $table->foreignUuid('wallet_account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->timestampTz('created_at');

            $table->index('wallet_transaction_id');
            $table->index('wallet_account_id');
        });

        TravoraSchema::addChecks('wallet_ledger_entries', [
            'wallet_ledger_entries_non_negative' => 'debit_minor >= 0 AND credit_minor >= 0',
            'wallet_ledger_entries_single_side' => '(debit_minor > 0 AND credit_minor = 0) OR (credit_minor > 0 AND debit_minor = 0)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('wallets');
    }
};
