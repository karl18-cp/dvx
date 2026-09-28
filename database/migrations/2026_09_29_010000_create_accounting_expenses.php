<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_key', 100)->unique();
            $table->timestamps();
        });
        Schema::create('accounting_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->date('expense_date')->index();
            $table->string('description', 200);
            $table->string('payee', 200)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_expenses');
        Schema::dropIfExists('expense_categories');
    }
};
