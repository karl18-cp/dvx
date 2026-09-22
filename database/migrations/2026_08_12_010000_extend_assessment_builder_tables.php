<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_training_materials', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->boolean('is_required')->default(true)->after('duration_seconds');
            $table->boolean('is_active')->default(true)->after('display_order');
        });

        Schema::table('assessment_assignments', function (Blueprint $table) {
            $table->foreignId('source_team_id')->nullable()->after('employee_id')->constrained('teams')->nullOnDelete();
            $table->index(['source_team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_assignments', function (Blueprint $table) {
            $table->dropForeign(['source_team_id']);
            $table->dropIndex(['source_team_id', 'status']);
            $table->dropColumn('source_team_id');
        });
        Schema::table('assessment_training_materials', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_required', 'is_active']);
        });
    }
};
