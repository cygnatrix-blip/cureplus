<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->string('sender_type', 10);
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_account_id')->nullable()->constrained('patient_accounts')->nullOnDelete();
            // Encrypted by the model, so it is text whatever the message length.
            $table->text('body')->nullable();
            $table->string('attachment_disk', 30)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 200)->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'appointment_id', 'id']);
            $table->index(['appointment_id', 'sender_type', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_messages');
    }
};
