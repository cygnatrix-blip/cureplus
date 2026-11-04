<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('hostname')->unique();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending_dns')->index();
            $table->json('dns_result')->nullable();
            $table->dateTime('dns_checked_at')->nullable();
            $table->string('ssl_status', 20)->nullable();
            $table->boolean('parked_in_hosting')->default(false);
            $table->dateTime('parked_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('setting_key', 80)->primary();
            // Encrypted as a whole, because this table holds the platform's API keys.
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            // Null means platform-wide data only, such as the medicine catalogue.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 12);
            // Only the hash is kept. A leaked database yields no working keys.
            $table->string('key_hash', 64)->unique();
            $table->json('scopes');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('tables_count')->default(0);
            $table->string('status', 20)->index();
            $table->string('trigger', 20)->default('schedule');
            $table->boolean('copied_offsite')->default(false);
            $table->string('error', 200)->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('restore_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 20);
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('tested_by')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('metric', 40);
            $table->unsignedBigInteger('value')->default(0);

            $table->unique(['metric_date', 'metric']);
        });

        Schema::create('breach_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('affected_count')->nullable();
            $table->dateTime('detected_at');
            $table->dateTime('board_notified_at')->nullable();
            $table->dateTime('patients_notified_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['breach_incidents', 'daily_metrics', 'restore_tests', 'backup_runs', 'api_keys', 'blocked_ips', 'platform_settings', 'custom_domains'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
