<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaching_records', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->after('campaign_name')->constrained()->nullOnDelete();
            $table->string('team_name')->nullable()->after('team_id');
            $table->index(['team_id', 'coaching_date'], 'coaching_team_date_index');
        });

        DB::table('coaching_records')->whereNull('team_id')->orderBy('id')->eachById(function (object $record): void {
            $team = DB::table('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')->where('team_members.user_id', $record->employee_id)->first(['teams.id', 'teams.name']);
            if ($team) {
                DB::table('coaching_records')->where('id', $record->id)->update(['team_id' => $team->id, 'team_name' => $team->name]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('coaching_records', function (Blueprint $table): void {
            $table->dropIndex('coaching_team_date_index');
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn('team_name');
        });
    }
};
