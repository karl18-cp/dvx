<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_backpays', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('separation_date');
            $table->string('separation_status', 20);
            $table->unsignedSmallInteger('calendar_year');
            $table->unsignedBigInteger('eligible_basic_cents')->default(0);
            $table->unsignedBigInteger('thirteenth_month_cents')->default(0);
            $table->unsignedBigInteger('last_payroll_cents')->default(0);
            $table->unsignedBigInteger('additions_cents')->default(0);
            $table->unsignedBigInteger('deductions_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->string('status', 30)->default('pending_clearance')->index();
            $table->date('claimed_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('source_snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'separation_date']);
            $table->index(['calendar_year', 'separation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_backpays');
    }
};
