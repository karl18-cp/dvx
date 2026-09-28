<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('source_trainee_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->dropUnique('users_email_unique');
            $table->index('email');
        });
    }

    public function down(): void
    {
        // Restoring email uniqueness requires resolving any linked accounts first.
        if (DB::table('users')->select('email')->groupBy('email')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Linked accounts share email addresses; resolve them before rolling back.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_trainee_id');
            $table->dropIndex('users_email_index');
            $table->unique('email');
        });
    }
};
