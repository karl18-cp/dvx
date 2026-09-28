<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_exam_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->default('Call Center Logic Exam');
            $table->text('instructions')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('question_count')->default(10);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedTinyInteger('passing_percent')->default(70);
            $table->timestamps();
        });
        Schema::create('applicant_exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 80);
            $table->text('prompt');
            $table->json('options');
            $table->unsignedTinyInteger('correct_index');
            $table->text('explanation')->nullable();
            $table->boolean('approved')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('applicant_exam_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('session_hash', 64)->index();
            $table->string('title');
            $table->json('questions');
            $table->json('answers')->nullable();
            $table->unsignedTinyInteger('passing_percent');
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('total');
            $table->boolean('passed')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('job_application_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_exam_attempts');
        Schema::dropIfExists('applicant_exam_questions');
        Schema::dropIfExists('applicant_exam_settings');
    }
};
