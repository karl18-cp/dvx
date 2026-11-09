<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('calendar_uid')->unique();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('interview_type', 30)->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('timezone', 60)->default('Asia/Manila');
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('status', 30)->default('scheduled')->index();
            $table->unsignedInteger('calendar_sequence')->default(0);
            $table->unsignedSmallInteger('reschedule_count')->default(0);
            $table->text('notes')->nullable();
            $table->string('applicant_invite_status', 20)->default('pending');
            $table->string('interviewer_invite_status', 20)->default('pending');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_invite_attempt_at')->nullable();
            $table->string('invite_failure_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['interviewer_id', 'starts_at', 'ends_at']);
            $table->index(['job_application_id', 'status']);
        });

        Schema::create('interview_appointment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_appointment_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30)->index();
            $table->timestamp('previous_starts_at')->nullable();
            $table->timestamp('previous_ends_at')->nullable();
            $table->timestamp('new_starts_at')->nullable();
            $table->timestamp('new_ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_appointment_events');
        Schema::dropIfExists('interview_appointments');
    }
};
