<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_answers', fn (Blueprint $table) => $table->text('grader_feedback')->nullable()->after('requires_manual_review'));
    }

    public function down(): void
    {
        Schema::table('assessment_answers', fn (Blueprint $table) => $table->dropColumn('grader_feedback'));
    }
};
