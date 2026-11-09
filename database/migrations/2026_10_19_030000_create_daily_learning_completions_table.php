<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_learning_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('learning_date');
            $table->boolean('is_correct')->default(false);
            $table->string('answer', 500)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'learning_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_learning_completions');
    }
};
