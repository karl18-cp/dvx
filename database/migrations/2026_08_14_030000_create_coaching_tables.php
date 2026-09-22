<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('assessment_skills')->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('coaching_date');
            $table->string('type', 50);
            $table->string('status', 30)->default('open');
            $table->text('summary');
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('action_plan')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'created_at']);
            $table->index(['coach_id', 'status', 'created_at']);
            $table->index(['status', 'follow_up_date']);
            $table->index(['skill_id', 'status']);
        });

        Schema::create('coaching_training_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coaching_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_material_id')->constrained('training_library_materials')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['coaching_record_id', 'library_material_id'], 'coaching_material_unique');
        });

        Schema::create('coaching_training_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coaching_training_assignment_id')->constrained(indexName: 'coaching_progress_assignment_fk')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->unsignedInteger('maximum_position_seconds')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['coaching_training_assignment_id', 'employee_id'], 'coaching_progress_employee_unique');
            $table->index(['employee_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_training_progress');
        Schema::dropIfExists('coaching_training_assignments');
        Schema::dropIfExists('coaching_records');
    }
};
