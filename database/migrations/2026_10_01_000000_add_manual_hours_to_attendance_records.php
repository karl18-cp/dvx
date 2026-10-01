<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', fn (Blueprint $table) => $table->json('manual_hours')->nullable());
    }

    public function down(): void
    {
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropColumn('manual_hours'));
    }
};
