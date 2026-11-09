<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 8, 2);
            $table->date('effective_date');
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_key')->unique();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('automatic')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'effective_date']);
            $table->index(['user_id', 'cycle_start', 'type']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_credit_transactions');
    }
};
