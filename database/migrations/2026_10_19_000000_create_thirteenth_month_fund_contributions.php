<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thirteenth_month_fund_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->unsignedSmallInteger('year')->index();
            $table->date('contribution_month')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thirteenth_month_fund_contributions');
    }
};
