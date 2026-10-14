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
        Schema::create('webhook_events', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->string('event_id')->nullable();
            $table->string('event_type', 100);
            $table->text('signature')->nullable();
            $table->jsonb('payload');
            $table->string('payload_hash', 128);
            $table->boolean('signature_valid')->default(false);
            $table->string('status', 20)->default('pending');
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable()->index();
            $table->timestampTz('created_at');

            $table->unique(['provider_id', 'payload_hash']);
            $table->index(['status', 'received_at']);
        });

        TravoraSchema::addChecks('webhook_events', [
            'webhook_events_attempt_count_non_negative' => 'attempt_count >= 0',
        ]);

        TravoraSchema::uniqueWhere(
            'webhook_events_provider_event_id_unique',
            'webhook_events',
            ['provider_id', 'event_id'],
            'event_id IS NOT NULL',
            'event_id IS NOT NULL',
        );

        Schema::create('notifications', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('promotions_enabled')->default(true);
            $table->boolean('usage_alerts_enabled')->default(true);
            $table->boolean('payment_alerts_enabled')->default(true);
            TravoraSchema::timestampsTz($table);
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('ticket_number', 40)->unique();
            $table->string('category', 40);
            $table->string('subject');
            $table->string('status', 30)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_message_at')->nullable();
            TravoraSchema::timestampsTz($table);
            $table->timestampTz('closed_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 20);
            $table->text('message');
            $table->timestampTz('created_at');

            $table->index(['ticket_id', 'created_at']);
            $table->index('sender_user_id');
        });

        Schema::create('support_attachments', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->string('file_name');
            $table->text('file_url');
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->timestampTz('created_at');

            $table->index('ticket_id');
            $table->index('message_id');
        });

        TravoraSchema::addChecks('support_attachments', [
            'support_attachments_file_size_non_negative' => 'file_size IS NULL OR file_size >= 0',
        ]);

        Schema::create('audit_logs', function (Blueprint $table): void {
            TravoraSchema::uuidPrimary($table);
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->index();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('webhook_events');
    }
};
