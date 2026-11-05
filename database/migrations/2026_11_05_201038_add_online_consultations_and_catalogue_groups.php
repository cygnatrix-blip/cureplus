<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Names used before the specialty list existed, moved onto it.
     *
     * @var array<string, string>
     */
    private const RENAMED_SPECIALTIES = ['General Medicine' => 'General Physician', 'Pediatrics' => 'Pediatrician', 'Dermatology' => 'Dermatologist'];

    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('mode', 20)->default('in_person')->after('kind');
            $table->string('video_link')->nullable()->after('mode');
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->boolean('offers_video')->default(false)->after('specialty');
            $table->string('video_link')->nullable()->after('offers_video');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->string('system', 40)->nullable()->after('manufacturer')->index();
            $table->string('specialty', 100)->nullable()->after('system')->index();
        });

        foreach (self::RENAMED_SPECIALTIES as $from => $to) {
            DB::table('doctor_profiles')->where('specialty', $from)->update(['specialty' => $to]);
            DB::table('templates')->where('specialty', $from)->update(['specialty' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMED_SPECIALTIES as $from => $to) {
            DB::table('doctor_profiles')->where('specialty', $to)->update(['specialty' => $from]);
            DB::table('templates')->where('specialty', $to)->update(['specialty' => $from]);
        }

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex(['system']);
            $table->dropIndex(['specialty']);
            $table->dropColumn(['system', 'specialty']);
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->dropColumn(['offers_video', 'video_link']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['mode', 'video_link']);
        });
    }
};
