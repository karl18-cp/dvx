<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'description')) {
            Schema::table('campaigns', fn (Blueprint $table) => $table->text('description')->nullable()->after('abbreviation'));
        }
        if (! Schema::hasColumn('campaigns', 'is_active')) {
            Schema::table('campaigns', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->after('description')->index('campaigns_active_index'));
        }

        foreach (['assessments', 'training_library_materials', 'assessment_bank_questions'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'applies_to_all_campaigns')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->boolean('applies_to_all_campaigns')->default(true)->index($tableName.'_all_campaigns_index'));
            }
        }

        $this->pivot('assessment_campaign', 'assessment_id', 'assessments');
        $this->pivot('training_library_material_campaign', 'training_library_material_id', 'training_library_materials');
        $this->pivot('assessment_bank_question_campaign', 'assessment_bank_question_id', 'assessment_bank_questions');

        $this->snapshot('assessment_assignments', 'fk_assessment_assignments_campaign_snapshot');
        $this->snapshot('assessment_attempts', 'fk_assessment_attempts_campaign_snapshot');
        $this->snapshot('coaching_records', 'fk_coaching_records_campaign_snapshot');
    }

    private function pivot(string $name, string $foreign, string $target): void
    {
        if (Schema::hasTable($name)) {
            return;
        }
        Schema::create($name, function (Blueprint $table) use ($name, $foreign, $target) {
            $table->foreignId($foreign)->constrained($target, indexName: $name.'_content_fk')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained(indexName: $name.'_campaign_fk')->restrictOnDelete();
            $table->primary([$foreign, 'campaign_id']);
        });
    }

    private function snapshot(string $tableName, string $foreignKey): void
    {
        if (! Schema::hasColumn($tableName, 'campaign_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKey) {
                $table->unsignedBigInteger('campaign_id')->nullable()->after('employee_id');
                $table->index('campaign_id', $foreignKey.'_idx');
                $table->foreign('campaign_id', $foreignKey)->references('id')->on('campaigns')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn($tableName, 'campaign_name')) {
            Schema::table($tableName, fn (Blueprint $table) => $table->string('campaign_name')->nullable()->after('campaign_id'));
        }
    }

    public function down(): void
    {
        Schema::table('coaching_records', fn (Blueprint $table) => $table->dropConstrainedForeignId('campaign_id'));
        Schema::table('assessment_attempts', fn (Blueprint $table) => $table->dropConstrainedForeignId('campaign_id'));
        Schema::table('assessment_assignments', fn (Blueprint $table) => $table->dropConstrainedForeignId('campaign_id'));
        Schema::dropIfExists('assessment_bank_question_campaign');
        Schema::dropIfExists('training_library_material_campaign');
        Schema::dropIfExists('assessment_campaign');
        foreach (['assessments', 'training_library_materials', 'assessment_bank_questions'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('applies_to_all_campaigns'));
        }
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropColumn(['description', 'is_active']));
    }
};
