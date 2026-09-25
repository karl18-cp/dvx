<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_number_sequences', function (Blueprint $table) {
            $table->string('prefix')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        foreach (['DVX' => 1, 'DVXTR' => 0] as $prefix => $minimum) {
            $highest = DB::table('users')->pluck('username')->map(fn ($username) => preg_match('/^'.$prefix.'(\d+)$/i', (string) $username, $matches) ? (int) $matches[1] : 0)->max() ?? 0;
            DB::table('employee_number_sequences')->insert(['prefix' => $prefix, 'last_number' => max($minimum, $highest)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_number_sequences');
    }
};
