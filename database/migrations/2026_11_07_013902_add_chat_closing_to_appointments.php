<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('chat_closed_at')->nullable()->after('completed_at');
            $table->foreignId('chat_closed_by')->nullable()->after('chat_closed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chat_closed_by');
            $table->dropColumn('chat_closed_at');
        });
    }
};
