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
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->text('avatar_url')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('preferred_language', 10)->default('en');
            $table->string('timezone', 64)->default('Africa/Tripoli');
            TravoraSchema::timestampsTz($table);
        });

        Schema::create('user_devices', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('device_name', 150)->nullable();
            $table->string('device_identifier')->nullable();
            $table->text('push_token')->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestampTz('last_seen_at')->nullable()->index();
            $table->timestampTz('revoked_at')->nullable();
            TravoraSchema::timestampsTz($table);

            $table->index(['user_id', 'platform']);
            $table->unique(['user_id', 'device_identifier']);
        });

        Schema::create('otp_verifications', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('target');
            $table->string('purpose', 30);
            $table->string('code_hash');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('consumed_at')->nullable()->index();
            $table->integer('attempts')->default(0);
            $table->timestampTz('created_at');

            $table->index(['target', 'purpose', 'channel']);
            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_profiles');
    }
};
