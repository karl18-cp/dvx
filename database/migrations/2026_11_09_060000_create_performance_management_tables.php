<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('category', 60)->default('performance');
            $table->text('description')->nullable();
            $table->decimal('baseline_value', 12, 2)->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('unit', 40)->nullable();
            $table->unsignedTinyInteger('weight')->default(0);
            $table->date('start_date');
            $table->date('due_date');
            $table->string('status', 24)->default('active');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('outcome')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'due_date']);
        });

        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->date('period_month');
            $table->string('status', 24)->default('draft');
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->json('kpis');
            $table->text('strengths')->nullable();
            $table->text('development_areas')->nullable();
            $table->text('manager_summary')->nullable();
            $table->text('employee_comments')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period_month']);
            $table->index(['employee_id', 'status', 'period_month']);
        });

        Schema::create('performance_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('scheduled_at');
            $table->timestamp('held_at')->nullable();
            $table->string('status', 24)->default('scheduled');
            $table->text('agenda');
            $table->text('manager_notes')->nullable();
            $table->text('employee_comments')->nullable();
            $table->json('action_items')->nullable();
            $table->date('next_meeting_date')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'scheduled_at']);
        });

        Schema::create('performance_improvement_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('source_review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->string('title');
            $table->text('reason');
            $table->text('expectations');
            $table->text('support_plan')->nullable();
            $table->json('success_criteria');
            $table->json('progress_updates')->nullable();
            $table->date('start_date');
            $table->date('review_date');
            $table->date('end_date');
            $table->string('status', 24)->default('draft');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'end_date']);
        });

        Schema::create('performance_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('record_type', 30);
            $table->unsignedBigInteger('record_id');
            $table->string('typed_name');
            $table->text('employee_comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['employee_id', 'record_type', 'record_id'], 'performance_ack_unique');
            $table->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_acknowledgements');
        Schema::dropIfExists('performance_improvement_plans');
        Schema::dropIfExists('performance_meetings');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_goals');
    }
};
