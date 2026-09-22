<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_evaluation_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('applies_to_all_campaigns')->default(false);
            $table->string('status', 20)->default('draft');
            $table->decimal('passing_score', 5, 2)->default(75);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'name']);
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('call_evaluation_scorecard_campaign', function (Blueprint $table) {
            $table->foreignId('call_evaluation_scorecard_id');
            $table->foreignId('campaign_id');
            $table->primary(['call_evaluation_scorecard_id', 'campaign_id'], 'call_scorecard_campaign_primary');
            $table->foreign('call_evaluation_scorecard_id', 'call_scorecard_campaign_scorecard_fk')->references('id')->on('call_evaluation_scorecards')->cascadeOnDelete();
            $table->foreign('campaign_id', 'call_scorecard_campaign_campaign_fk')->references('id')->on('campaigns')->cascadeOnDelete();
        });

        Schema::create('call_evaluation_scorecard_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('call_evaluation_scorecards')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['scorecard_id', 'display_order'], 'call_scorecard_category_order_index');
        });

        Schema::create('call_evaluation_scorecard_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('call_evaluation_scorecard_categories')->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('assessment_skills')->nullOnDelete();
            $table->string('label');
            $table->text('guidance')->nullable();
            $table->decimal('points_possible', 8, 2);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_critical')->default(false);
            $table->boolean('allows_na')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['category_id', 'display_order'], 'call_scorecard_criterion_order_index');
            $table->index(['skill_id', 'category_id'], 'call_scorecard_criterion_skill_index');
        });

        Schema::create('call_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('scorecard_id')->nullable()->constrained('call_evaluation_scorecards')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('campaign_name');
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('team_name');
            $table->string('scorecard_name');
            $table->json('scorecard_snapshot');
            $table->dateTime('call_at');
            $table->string('call_direction', 20);
            $table->string('call_reference')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_key', 1024)->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('overall_feedback')->nullable();
            $table->text('recommended_action')->nullable();
            $table->decimal('points_earned', 10, 2)->nullable();
            $table->decimal('points_possible', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('result', 20)->nullable();
            $table->boolean('has_critical_failure')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'call_at']);
            $table->index(['evaluator_id', 'status', 'created_at']);
            $table->index(['campaign_id', 'status', 'call_at']);
            $table->index(['team_id', 'status', 'call_at']);
            $table->index(['scorecard_id', 'status', 'call_at']);
            $table->index(['result', 'finalized_at']);
            $table->index('call_reference');
        });

        Schema::create('call_evaluation_criterion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('criterion_snapshot_key', 80);
            $table->foreignId('criterion_id')->nullable()->constrained('call_evaluation_scorecard_criteria')->nullOnDelete();
            $table->decimal('points_awarded', 8, 2)->nullable();
            $table->boolean('is_na')->default(false);
            $table->boolean('critical_failure')->default(false);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['call_evaluation_id', 'criterion_snapshot_key'], 'call_evaluation_criterion_unique');
            $table->index(['criterion_id', 'critical_failure'], 'call_criterion_result_critical_index');
        });

        Schema::table('coaching_records', function (Blueprint $table) {
            $table->foreignId('call_evaluation_id')->nullable()->after('assessment_id')->constrained()->nullOnDelete();
            $table->unique('call_evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::table('coaching_records', fn (Blueprint $table) => $table->dropConstrainedForeignId('call_evaluation_id'));
        Schema::dropIfExists('call_evaluation_criterion_results');
        Schema::dropIfExists('call_evaluations');
        Schema::dropIfExists('call_evaluation_scorecard_criteria');
        Schema::dropIfExists('call_evaluation_scorecard_categories');
        Schema::dropIfExists('call_evaluation_scorecard_campaign');
        Schema::dropIfExists('call_evaluation_scorecards');
    }
};
