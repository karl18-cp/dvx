<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_bank_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->nullable()->constrained('assessment_skills')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('assessment_categories')->nullOnDelete();
            $table->text('question_text');
            $table->string('question_type', 30);
            $table->string('difficulty', 10);
            $table->decimal('points', 8, 2);
            $table->text('feedback')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'skill_id', 'difficulty']);
            $table->index(['status', 'category_id', 'question_type']);
        });
        Schema::create('assessment_bank_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_question_id')->constrained('assessment_bank_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('display_order');
            $table->timestamps();
            $table->index(['bank_question_id', 'display_order'], 'assessment_bank_option_order_index');
        });
        Schema::create('assessment_random_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('assessment_skills')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('assessment_categories')->nullOnDelete();
            $table->string('difficulty', 10)->nullable();
            $table->string('question_type', 30)->nullable();
            $table->unsignedSmallInteger('questions_to_select');
            $table->decimal('points_per_question', 8, 2);
            $table->unsignedInteger('display_order');
            $table->timestamps();
            $table->index(['assessment_id', 'display_order']);
        });
        Schema::table('assessments', fn (Blueprint $table) => $table->boolean('reduce_repeated_questions')->default(true)->after('randomize_answers'));
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreignId('source_bank_question_id')->nullable()->after('skill_id')->constrained('assessment_bank_questions')->nullOnDelete();
            $table->foreignId('generated_for_attempt_id')->nullable()->after('source_bank_question_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->index(['generated_for_attempt_id', 'source_bank_question_id'], 'assessment_generated_bank_index');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropForeign(['source_bank_question_id']);
            $table->dropForeign(['generated_for_attempt_id']);
            $table->dropColumn(['source_bank_question_id', 'generated_for_attempt_id']);
        });
        Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn('reduce_repeated_questions'));
        Schema::dropIfExists('assessment_random_pools');
        Schema::dropIfExists('assessment_bank_question_options');
        Schema::dropIfExists('assessment_bank_questions');
    }
};
