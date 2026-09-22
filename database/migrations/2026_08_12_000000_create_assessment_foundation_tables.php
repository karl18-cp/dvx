<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('assessment_skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('assessment_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('difficulty', 20)->default('beginner');
            $table->decimal('passing_score', 5, 2)->default(75);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedSmallInteger('maximum_attempts')->default(1);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_retake')->default(false);
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('randomize_answers')->default(false);
            $table->boolean('show_correct_answers')->default(false);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
            $table->index(['category_id', 'status']);
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('assessment_training_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 30);
            $table->text('content')->nullable();
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_key', 1024)->nullable();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedTinyInteger('required_completion_percentage')->default(90);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['assessment_id', 'display_order']);
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('assessment_skills')->nullOnDelete();
            $table->text('question_text');
            $table->string('question_type', 30);
            $table->decimal('points', 8, 2)->default(1);
            $table->text('correct_answer')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->index(['assessment_id', 'display_order']);
            $table->index(['skill_id', 'assessment_id']);
        });

        Schema::create('assessment_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['question_id', 'display_order']);
        });

        Schema::create('assessment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('available_from')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->string('status', 20)->default('assigned');
            $table->timestamps();
            $table->unique(['assessment_id', 'employee_id']);
            $table->index(['employee_id', 'status', 'due_date']);
            $table->index(['assessment_id', 'status']);
        });

        Schema::create('assessment_training_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('assessment_training_materials')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->unsignedInteger('maximum_position_seconds')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['material_id', 'employee_id']);
            $table->index(['employee_id', 'completed_at']);
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignment_id')->constrained('assessment_assignments')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->decimal('points_earned', 10, 2)->nullable();
            $table->decimal('total_points', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedInteger('time_taken_seconds')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'attempt_number']);
            $table->index(['employee_id', 'status']);
            $table->index(['employee_id', 'assessment_id']);
            $table->index(['assessment_id', 'submitted_at']);
        });

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->json('answer')->nullable();
            $table->decimal('points_awarded', 8, 2)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->boolean('requires_manual_review')->default(false);
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
            $table->index(['question_id', 'is_correct']);
        });

        Schema::create('assessment_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('target_type', 100);
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id', 'created_at'], 'assessment_activity_target_index');
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_activity_logs');
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_training_progress');
        Schema::dropIfExists('assessment_assignments');
        Schema::dropIfExists('assessment_question_options');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessment_training_materials');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_skills');
        Schema::dropIfExists('assessment_categories');
    }
};
