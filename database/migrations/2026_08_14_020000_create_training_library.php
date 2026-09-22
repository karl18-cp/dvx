<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_library_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('assessment_categories')->nullOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('assessment_skills')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->longText('content')->nullable();
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_key', 1024)->nullable();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'type']);
            $table->index(['status', 'category_id']);
            $table->index(['status', 'skill_id']);
        });
        Schema::create('assessment_training_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_material_id')->constrained('training_library_materials')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedTinyInteger('required_completion_percentage')->default(90);
            $table->unsignedInteger('display_order');
            $table->timestamps();
            $table->unique(['assessment_id', 'library_material_id'], 'assessment_library_material_unique');
            $table->index(['assessment_id', 'display_order'], 'assessment_attachment_order_index');
        });
        Schema::create('assessment_training_attachment_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('assessment_training_attachments')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->unsignedInteger('maximum_position_seconds')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['attachment_id', 'employee_id'], 'attachment_employee_unique');
            $table->index(['employee_id', 'completed_at'], 'attachment_progress_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_training_attachment_progress');
        Schema::dropIfExists('assessment_training_attachments');
        Schema::dropIfExists('training_library_materials');
    }
};
