<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('creator_name');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->json('checklist');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tracker_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignee_name');
            $table->string('assignee_role');
            $table->json('completed_items');
            $table->string('status', 30)->default('open');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['tracker_task_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_task_assignments');
        Schema::dropIfExists('tracker_tasks');
    }
};
