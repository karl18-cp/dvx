<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->json('schedule_snapshot')->nullable();
            foreach (['time_in', 'lunch_out', 'lunch_in', 'time_out'] as $field) {
                $table->dateTime('actual_'.$field)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['schedule_snapshot', 'actual_time_in', 'actual_lunch_out', 'actual_lunch_in', 'actual_time_out']);
        });
    }
};
