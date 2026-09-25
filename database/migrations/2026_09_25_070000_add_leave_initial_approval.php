<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->unique();
            $table->boolean('requires_leader_approval')->default(false);
            $table->string('leader_status', 20)->default('not_required');
            $table->foreignId('leader_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('leader_name')->nullable();
            $table->timestamp('leader_reviewed_at')->nullable();
            $table->text('leader_notes')->nullable();
        });
        DB::table('leave_requests')->whereIn('status', ['needs_review', 'pending'])->whereIn('user_id', DB::table('users')->select('id')->where('role', 'agent'))->update(['requires_leader_approval' => true, 'leader_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leader_reviewed_by');
            $table->dropUnique(['request_id']);
            $table->dropColumn(['request_id', 'requires_leader_approval', 'leader_status', 'leader_name', 'leader_reviewed_at', 'leader_notes']);
        });
    }
};
