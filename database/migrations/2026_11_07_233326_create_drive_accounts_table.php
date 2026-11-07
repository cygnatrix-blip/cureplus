<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Practices can connect Google accounts so patient files are copied into their own Drive.
 * Files keep their server copy until a platform admin removes it with the practice's consent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('google_email', 190);
            $table->text('refresh_token')->nullable();
            $table->text('access_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->string('folder_id', 100)->nullable();
            $table->unsignedBigInteger('quota_total')->nullable();
            $table->unsignedBigInteger('quota_used')->default(0);
            $table->dateTime('quota_checked_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('last_error', 255)->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'google_email']);
        });

        foreach (['visit_attachments', 'appointment_messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('drive_account_id')->nullable()->constrained('drive_accounts');
                $table->string('drive_file_id', 100)->nullable();
                $table->dateTime('drive_synced_at')->nullable();
                $table->dateTime('server_removed_at')->nullable();
                $table->index(['organization_id', 'drive_synced_at']);
            });
        }

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('drive_server_copy', 10)->nullable();
            $table->dateTime('drive_server_copy_decided_at')->nullable();
            $table->string('drive_server_copy_note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['drive_server_copy', 'drive_server_copy_decided_at', 'drive_server_copy_note']);
        });

        foreach (['visit_attachments', 'appointment_messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['organization_id', 'drive_synced_at']);
                $table->dropConstrainedForeignId('drive_account_id');
                $table->dropColumn(['drive_file_id', 'drive_synced_at', 'server_removed_at']);
            });
        }

        Schema::dropIfExists('drive_accounts');
    }
};
