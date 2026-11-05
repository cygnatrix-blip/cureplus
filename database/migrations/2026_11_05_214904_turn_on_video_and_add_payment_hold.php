<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->boolean('offers_video')->default(true)->change();
        });

        DB::table('doctor_profiles')->update(['offers_video' => true]);

        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('pay_by')->nullable()->after('video_link')->index();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('doctor_specialty', 100)->nullable()->after('doctor_qualification');
            $table->boolean('is_teleconsultation')->default(false)->after('doctor_specialty');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['doctor_specialty', 'is_teleconsultation']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['pay_by']);
            $table->dropColumn('pay_by');
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->boolean('offers_video')->default(false)->change();
        });
    }
};
