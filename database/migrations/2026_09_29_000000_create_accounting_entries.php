<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('kind', 30);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('training_plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 200);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('gross_cents');
            $table->unsignedBigInteger('deduction_cents')->default(0);
            $table->unsignedBigInteger('net_cents');
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 150)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind', 'period_start', 'period_end']);
        });
        Schema::create('accounting_allowance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('accounting_entries')->restrictOnDelete();
            $table->foreignId('training_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('training_date');
            $table->unique(['training_plan_id', 'user_id', 'training_date'], 'accounting_allowance_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_allowance_days');
        Schema::dropIfExists('accounting_entries');
    }
};
