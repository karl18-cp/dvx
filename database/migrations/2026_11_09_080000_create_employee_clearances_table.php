<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->date('separation_date');
            $table->string('separation_type', 20);
            $table->string('status', 24)->default('in_progress')->index();
            $table->string('property_status', 24)->default('pending');
            $table->json('property_items')->nullable();
            $table->text('property_notes')->nullable();
            $table->string('final_attendance_status', 24)->default('pending');
            $table->foreignId('final_attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->text('final_attendance_notes')->nullable();
            $table->string('outstanding_status', 24)->default('pending');
            $table->unsignedBigInteger('outstanding_amount_cents')->default(0);
            $table->text('outstanding_notes')->nullable();
            $table->string('quitclaim_status', 24)->default('pending');
            $table->foreignId('quitclaim_contract_id')->nullable()->constrained('employee_contracts')->nullOnDelete();
            $table->text('quitclaim_notes')->nullable();
            $table->string('exit_interview_status', 24)->default('pending');
            $table->dateTime('exit_interview_at')->nullable();
            $table->text('exit_interview_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_clearances');
    }
};
