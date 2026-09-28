<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->json('weekdays');
            $table->json('phases');
            $table->unsignedSmallInteger('first_allowance_day');
            $table->string('allowance_basis', 20)->default('attended');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('training_plan_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_enrollments');
        Schema::dropIfExists('training_plans');
    }
};
