<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('position');
            $table->string('location')->nullable();
            $table->unsignedTinyInteger('years_experience')->default(0);
            $table->text('message')->nullable();
            $table->string('resume_disk', 40)->nullable();
            $table->string('resume_path', 1024)->nullable();
            $table->string('resume_original_name')->nullable();
            $table->string('resume_mime', 100)->nullable();
            $table->unsignedBigInteger('resume_size')->nullable();
            $table->string('screening_status', 20)->default('pending');
            $table->string('interview_status', 20)->default('pending');
            $table->string('application_status', 30)->default('new');
            $table->text('internal_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['application_status', 'created_at']);
            $table->index(['screening_status', 'interview_status']);
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
