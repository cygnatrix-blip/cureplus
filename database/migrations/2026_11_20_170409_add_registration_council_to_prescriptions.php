<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A prescription records the doctor's council as it was, beside the registration number,
 * because a registration number only identifies a doctor together with its council.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('doctor_registration_council', 150)->nullable()->after('doctor_registration_number');
        });

        // Prescriptions already issued take the council their doctor has on file now.
        DB::table('prescriptions')->whereNull('doctor_registration_council')->orderBy('id')
            ->each(function (object $prescription): void {
                $council = DB::table('doctor_profiles')->where('user_id', $prescription->doctor_user_id)->value('registration_council');

                if ($council !== null) {
                    DB::table('prescriptions')->where('id', $prescription->id)->update(['doctor_registration_council' => $council]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('doctor_registration_council');
        });
    }
};
