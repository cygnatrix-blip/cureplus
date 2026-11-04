<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('event', 40);
            $table->string('recipient_masked', 120)->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('error', 120)->nullable();
            $table->string('provider_id', 120)->nullable();
            // Each planned message has one row. The unique key is what stops a retry from
            // messaging a patient twice. NULL means "not deduplicated", which MariaDB allows repeatedly.
            $table->string('dedupe_key', 120)->nullable()->unique();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['channel', 'status', 'created_at']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscriber_type', 30);
            $table->unsignedBigInteger('subscriber_id');
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['subscriber_type', 'subscriber_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('subscriber_type', 30);
            $table->unsignedBigInteger('subscriber_id');
            $table->json('muted_events');
            $table->timestamps();

            $table->unique(['subscriber_type', 'subscriber_id'], 'notification_preferences_subscriber_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('message_logs');
    }
};
