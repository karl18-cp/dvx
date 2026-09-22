<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->boolean('simple_builder_enabled')->default(false)->after('status');
            $table->unsignedSmallInteger('planned_question_count')->nullable()->after('simple_builder_enabled');
            $table->unsignedInteger('planned_total_points')->nullable()->after('planned_question_count');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn([
            'simple_builder_enabled', 'planned_question_count', 'planned_total_points',
        ]));
    }
};
