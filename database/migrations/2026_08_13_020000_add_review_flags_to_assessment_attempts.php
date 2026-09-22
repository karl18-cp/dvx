<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', fn (Blueprint $table) => $table->json('flagged_question_ids')->nullable()->after('question_snapshot'));
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', fn (Blueprint $table) => $table->dropColumn('flagged_question_ids'));
    }
};
