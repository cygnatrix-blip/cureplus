<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Null doctor means the organization's default for that visit type.
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('visit_type', 20);
            $table->unsignedBigInteger('amount_paise');
            $table->timestamps();

            $table->unique(['organization_id', 'doctor_user_id', 'visit_type']);
        });

        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('price_paise');
            $table->boolean('is_taxable')->default(false);
            // Basis points: 1800 is 18%. Integer maths, so tax never picks up float errors.
            $table->unsignedSmallInteger('gst_rate_bp')->default(0);
            $table->string('sac_hsn_code', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('kind', 20);
            $table->string('status', 20)->default('unpaid');
            $table->unsignedBigInteger('subtotal_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->string('discount_reason')->nullable();
            $table->unsignedBigInteger('exempt_paise')->default(0);
            $table->unsignedBigInteger('taxable_paise')->default(0);
            $table->unsignedBigInteger('tax_paise')->default(0);
            $table->unsignedBigInteger('total_paise');
            $table->unsignedBigInteger('paid_paise')->default(0);
            $table->unsignedBigInteger('refunded_paise')->default(0);
            $table->string('gstin_snapshot', 15)->nullable();
            $table->dateTime('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'issued_at']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'patient_id']);
            $table->index(['organization_id', 'doctor_user_id']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('sac_hsn_code', 12)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->unsignedSmallInteger('gst_rate_bp')->default(0);
            $table->unsignedBigInteger('tax_paise')->default(0);
            $table->unsignedBigInteger('line_total_paise');
            $table->timestamps();

            $table->index(['organization_id', 'invoice_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->string('mode', 20);
            $table->string('status', 20)->default('captured');
            $table->string('reference')->nullable();
            $table->string('gateway_link_id', 60)->nullable();
            $table->string('gateway_link_url')->nullable();
            // Unique with the organization: a repeated webhook cannot credit an invoice twice.
            $table->string('gateway_payment_id', 60)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'gateway_payment_id']);
            $table->index(['organization_id', 'gateway_link_id']);
            $table->index(['organization_id', 'paid_at']);
            $table->index(['organization_id', 'invoice_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->string('reason');
            $table->string('status', 20)->default('processed');
            $table->string('gateway_refund_id', 60)->nullable();
            $table->dateTime('refunded_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'gateway_refund_id']);
            $table->index(['organization_id', 'refunded_at']);
        });

        Schema::create('organization_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            // The whole configuration is encrypted as one value: keys never sit in plain text.
            $table->text('config');
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 20)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'kind']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('event_id', 100);
            $table->string('event_type', 60)->nullable();
            $table->string('status', 20);
            $table->string('detail')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['provider', 'organization_id', 'event_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_events', 'organization_integrations', 'refunds', 'payments', 'invoice_items', 'invoices', 'service_items', 'doctor_fees'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
