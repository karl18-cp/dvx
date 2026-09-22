<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_leader_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        DB::table('teams')->whereNotNull('team_leader_id')->orderBy('id')->each(function (object $team): void {
            DB::table('team_leader_assignments')->insert([
                'team_id' => $team->id, 'user_id' => $team->team_leader_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_leader_id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::table('team_leader_assignments')->orderBy('id')->each(function (object $assignment): void {
            DB::table('teams')->where('id', $assignment->team_id)->update(['team_leader_id' => $assignment->user_id]);
        });
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('team_leader_assignments');
    }
};
