<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
            $table->string('verification_note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('doctor_profiles', fn (Blueprint $table) => $table->dropColumn(['photo_path', 'verification_note']));
    }
};
