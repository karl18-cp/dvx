<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->json('question_snapshot')->nullable()->after('attempt_number');
            $table->timestamp('expires_at')->nullable()->after('started_at')->index();
        });

        Schema::table('assessment_answers', function (Blueprint $table) {
            $table->json('question_snapshot')->nullable()->after('question_id');
        });

        Schema::create('assessment_skill_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('assessment_skills')->restrictOnDelete();
            $table->decimal('points_earned', 10, 2)->default(0);
            $table->decimal('points_possible', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['attempt_id', 'skill_id']);
            $table->index(['skill_id', 'percentage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_skill_results');
        Schema::table('assessment_answers', fn (Blueprint $table) => $table->dropColumn('question_snapshot'));
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['question_snapshot', 'expires_at']);
        });
    }
};
