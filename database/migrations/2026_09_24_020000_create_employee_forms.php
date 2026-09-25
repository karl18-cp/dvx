<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_forms', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('ranking_enabled')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->json('fields');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('employee_form_team', function (Blueprint $table) {
            $table->foreignId('employee_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->primary(['employee_form_id', 'team_id']);
        });
        Schema::create('employee_form_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('employee_form_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name');
            $table->string('employee_username')->nullable();
            $table->string('form_title', 200);
            $table->text('form_description')->nullable();
            $table->unsignedInteger('revision');
            $table->boolean('ranking_enabled')->default(false);
            $table->json('teams');
            $table->unsignedTinyInteger('points')->default(0);
            $table->json('answers');
            $table->timestamps();
            $table->index(['employee_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_form_responses');
        Schema::dropIfExists('employee_form_team');
        Schema::dropIfExists('employee_forms');
    }
};
