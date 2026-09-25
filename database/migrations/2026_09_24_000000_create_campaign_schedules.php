<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('campaign_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->boolean('no_schedule')->default(true);
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->unique(['campaign_schedule_id', 'day']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('campaign_schedule_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('campaign_schedule_id'));
        Schema::dropIfExists('campaign_schedule_days');
        Schema::dropIfExists('campaign_schedules');
    }
};
