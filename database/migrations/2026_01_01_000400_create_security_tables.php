<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 30);
            // Keyed hashes: neither the phone number nor the code is readable from this table.
            $table->string('identifier_hash', 64);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            // dateTime, not timestamp: MariaDB 10.5 gives the first non-null TIMESTAMP in a table
            // ON UPDATE CURRENT_TIMESTAMP, so counting a wrong guess would rewrite the expiry.
            $table->dateTime('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['purpose', 'identifier_hash']);
        });

        // Append-only. The AuditLog model refuses updates and deletes.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->unsignedBigInteger('impersonator_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->index();

            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
            $table->index('action');
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('guard', 20)->nullable();
            $table->string('identifier_masked', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->index();
        });

        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->string('name', 40)->primary();
            $table->dateTime('beat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('otp_codes');
    }
};
