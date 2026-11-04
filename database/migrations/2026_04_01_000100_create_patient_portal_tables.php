<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_accounts', function (Blueprint $table) {
            $table->id();
            // The login. A mobile is kept only as a contact number.
            $table->string('email')->unique();
            $table->string('mobile', 20)->nullable();
            $table->string('name')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('status', 20)->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('patients', function (Blueprint $table) {
            // Links one person's separate records for the portal only. Records are never merged.
            $table->foreignId('patient_account_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('accepts_online_booking')->default(true);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('icon_192_path')->nullable();
            $table->string('icon_512_path')->nullable();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_account_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->foreignId('grantee_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('grantee_doctor_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('granted_via', 20);
            $table->dateTime('granted_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['patient_account_id', 'revoked_at']);
            $table->index(['grantee_organization_id', 'grantee_doctor_user_id']);
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('consents');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['icon_192_path', 'icon_512_path']));
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('accepts_online_booking'));
        Schema::table('patients', fn (Blueprint $table) => $table->dropConstrainedForeignId('patient_account_id'));
        Schema::dropIfExists('patient_accounts');
    }
};
