<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->unsignedBigInteger('price_paise')->default(0);
            $table->string('billing_interval', 10)->default('month');
            $table->unsignedSmallInteger('trial_days')->default(14);
            // Null limit means unlimited.
            $table->json('limits');
            $table->json('features');
            $table->string('razorpay_plan_id', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->unsignedSmallInteger('extra_trial_days')->default(0);
            $table->string('razorpay_offer_id', 60)->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('current_period_ends_at')->nullable();
            $table->dateTime('grace_ends_at')->nullable();
            $table->string('razorpay_subscription_id', 60)->nullable()->index();
            $table->string('checkout_url')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('status', 20)->default('paid');
            $table->string('razorpay_payment_id', 60)->nullable()->unique();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'paid_at']);
            $table->index('paid_at');
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 40);
            // Exactly one of these is set: a per-organization override, or a per-plan rule.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('plan_code', 40)->nullable();
            $table->boolean('is_enabled');
            $table->timestamps();

            $table->unique(['feature', 'organization_id', 'plan_code']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('category', 30)->default('general');
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('assigned_admin_id')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 20);
            $table->string('author_name');
            $table->text('body');
            $table->timestamps();

            $table->index(['organization_id', 'support_ticket_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            // Null means every organization.
            $table->json('organization_ids')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->primary(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['announcement_reads', 'announcements', 'ticket_messages', 'support_tickets', 'feature_flags', 'platform_invoices', 'organization_subscriptions', 'coupons', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
