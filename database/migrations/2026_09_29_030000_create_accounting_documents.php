<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('direction', 15)->index();
            $table->string('invoice_number', 100)->nullable();
            $table->string('invoice_key', 64)->nullable()->unique();
            $table->string('party_name', 200);
            $table->string('party_email', 200)->nullable();
            $table->string('description', 250);
            $table->date('issue_date');
            $table->date('due_date')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('accounting_document_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('accounting_documents')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->date('payment_date');
            $table->string('method', 25);
            $table->string('reference', 150);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_document_payments');
        Schema::dropIfExists('accounting_documents');
    }
};
