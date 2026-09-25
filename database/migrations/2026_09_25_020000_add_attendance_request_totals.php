<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', fn (Blueprint $table) => $table->boolean('is_paid')->nullable());
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->unsignedInteger('leave_minutes')->default(0);
            $table->unsignedInteger('total_minutes')->nullable();
            $table->json('approval_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', fn (Blueprint $table) => $table->dropColumn('is_paid'));
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropColumn(['worked_minutes', 'leave_minutes', 'total_minutes', 'approval_snapshot']));
    }
};
