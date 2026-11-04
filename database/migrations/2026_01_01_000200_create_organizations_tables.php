<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            // Public identifier (webhook URLs, admin links): numeric ids reveal platform size.
            $table->uuid('uuid')->unique();
            $table->string('type', 20)->index();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('plan', 50)->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('gstin', 15)->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('timings')->nullable();
            $table->json('letterhead')->nullable();
            $table->json('rx_pad')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            // Used by /{org-slug}/{doctor-slug}; the same doctor can differ per organization.
            $table->string('doctor_slug', 80)->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'doctor_slug']);
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('membership_location', function (Blueprint $table) {
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->primary(['membership_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_location');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('organizations');
    }
};
