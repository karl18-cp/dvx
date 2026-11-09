<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('locked_by')->nullable()->after('approved_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->foreignId('parent_entry_id')->nullable()->after('training_plan_id')->constrained('accounting_entries')->restrictOnDelete();
            $table->string('correction_type', 20)->nullable()->after('parent_entry_id');
            $table->string('correction_direction', 10)->nullable()->after('correction_type');
            $table->text('correction_reason')->nullable()->after('correction_direction');
            $table->index(['parent_entry_id', 'correction_type']);
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropIndex(['parent_entry_id', 'correction_type']);
            $table->dropConstrainedForeignId('parent_entry_id');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['submitted_at', 'locked_at', 'correction_type', 'correction_direction', 'correction_reason']);
        });
    }
};
