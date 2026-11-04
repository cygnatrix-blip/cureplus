<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('resubmitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
        });

        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 60);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });

        Schema::table('patients', function (Blueprint $table) {
            // Sample records for trying the product before approval. Deleted on approval.
            $table->boolean('is_demo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('patients', fn (Blueprint $table) => $table->dropColumn('is_demo'));
        Schema::dropIfExists('organization_documents');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['rejection_reason', 'rejected_at', 'resubmitted_at', 'reviewed_by']));
    }
};
