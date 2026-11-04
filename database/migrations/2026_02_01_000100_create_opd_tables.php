<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('counter_key', 80);
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'counter_key']);
        });

        Schema::table('locations', function (Blueprint $table) {
            // Lets a reception TV show the queue with no staff login. It only ever reveals token numbers.
            $table->string('display_token', 64)->nullable()->unique();
        });

        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('qualification')->nullable();
            $table->string('specialty', 100)->nullable();
            $table->string('registration_number', 60)->nullable()->index();
            $table->string('registration_council', 120)->nullable();
            $table->string('verification_status', 20)->default('unverified')->index();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('patient_code', 20);
            $table->string('name');
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('dob_is_estimated')->default(false);
            $table->string('gender', 10)->nullable();
            $table->string('relation', 40)->nullable();
            $table->string('address')->nullable();
            // Encrypted at rest; the keyed hash is what makes it searchable.
            $table->text('abha_id')->nullable();
            $table->string('abha_hash', 64)->nullable();
            $table->text('allergies')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'patient_code']);
            $table->index(['organization_id', 'mobile']);
            $table->index(['organization_id', 'name']);
            $table->index(['organization_id', 'abha_hash']);
        });

        Schema::create('doctor_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['doctor_user_id', 'weekday', 'is_active']);
            $table->index(['organization_id', 'location_id']);
        });

        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
            // Only the doctor personally can block time at every organization.
            $table->boolean('applies_everywhere')->default(false);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['doctor_user_id', 'starts_at', 'ends_at']);
            $table->index(['organization_id', 'starts_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20)->default('booked');
            $table->string('source', 20)->default('staff');
            $table->string('visit_type', 20)->default('new');
            $table->date('scheduled_on');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            // Set while a slot is held, null otherwise. MariaDB allows many NULLs in a unique
            // index, so this makes double-booking impossible even if application code has a bug.
            $table->string('slot_key', 12)->nullable();
            $table->unsignedInteger('token_number');
            $table->string('status', 20)->default('booked');
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['doctor_user_id', 'slot_key']);
            $table->unique(['organization_id', 'location_id', 'doctor_user_id', 'scheduled_on', 'token_number'], 'appointments_token_unique');
            $table->index(['organization_id', 'scheduled_on', 'status']);
            $table->index(['doctor_user_id', 'starts_at', 'ends_at']);
            $table->index(['organization_id', 'patient_id']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visited_on');
            $table->text('complaints')->nullable();
            $table->text('history')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'patient_id', 'visited_on']);
            $table->index(['organization_id', 'visited_on']);
            $table->index(['organization_id', 'doctor_user_id']);
        });

        Schema::create('vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->decimal('temperature_f', 4, 1)->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'visit_id']);
        });

        Schema::create('visit_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 80);
            $table->unsignedInteger('size_bytes');
            $table->string('category', 30)->default('other');
            $table->boolean('patient_visible')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'visit_id']);
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            // Null organization means a platform default that everyone can use.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('specialty', 100)->nullable();
            $table->string('name');
            $table->string('template_key', 60)->nullable();
            $table->json('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'organization_id']);
            $table->index('template_key');
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('generic_name')->index();
            $table->string('brand_name')->nullable()->index();
            $table->string('strength', 60)->nullable();
            $table->string('form', 40)->nullable();
            $table->string('manufacturer')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('favourite_medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'medicine_id']);
        });

        Schema::create('medicine_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('generic_name');
            $table->string('brand_name')->nullable();
            $table->string('strength', 60)->nullable();
            $table->string('form', 40)->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            // Copied in: a printed Rx must keep what was true on the day it was issued.
            $table->string('doctor_name');
            $table->string('doctor_qualification')->nullable();
            $table->string('doctor_registration_number', 60)->nullable();
            $table->text('lab_advice')->nullable();
            $table->text('patient_instructions')->nullable();
            $table->string('instructions_language', 5)->default('en');
            $table->date('follow_up_on')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('visit_id');
            $table->index(['organization_id', 'patient_id']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medicine_name');
            $table->string('dosage', 30)->nullable();
            $table->string('timing', 30)->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'prescription_id']);
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->date('due_on');
            $table->string('status', 20)->default('pending');
            $table->string('call_notes')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'due_on']);
            $table->index(['status', 'due_on']);
        });
    }

    public function down(): void
    {
        foreach ([
            'follow_ups', 'prescription_items', 'prescriptions', 'medicine_requests', 'favourite_medicines', 'medicines',
            'templates', 'visit_attachments', 'vitals', 'visits', 'appointments', 'calendar_blocks', 'doctor_availability',
            'patients', 'doctor_profiles', 'organization_counters',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('display_token'));
    }
};
