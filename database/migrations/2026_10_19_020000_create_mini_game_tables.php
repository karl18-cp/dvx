<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mini_game_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('game_type', 40)->index();
            $table->string('status', 20)->default('waiting')->index();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('current_turn_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('state')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_move_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mini_game_room_players', function (Blueprint $table): void {
            $table->id();
            $table->uuid('room_id');
            $table->foreign('room_id')->references('id')->on('mini_game_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');
            $table->string('symbol', 12)->nullable();
            $table->string('status', 20)->default('joined');
            $table->timestamps();
            $table->unique(['room_id', 'user_id']);
            $table->unique(['room_id', 'seat']);
        });

        Schema::create('mini_game_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('room_id');
            $table->foreign('room_id')->references('id')->on('mini_game_rooms')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['room_id', 'recipient_id']);
        });

        Schema::create('mini_game_presences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('available')->index();
            $table->uuid('current_room_id')->nullable()->index();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });

        Schema::create('mini_game_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('game_type', 40);
            $table->unsignedInteger('played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->integer('rating')->default(1000);
            $table->timestamps();
            $table->unique(['user_id', 'game_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mini_game_stats');
        Schema::dropIfExists('mini_game_presences');
        Schema::dropIfExists('mini_game_invitations');
        Schema::dropIfExists('mini_game_room_players');
        Schema::dropIfExists('mini_game_rooms');
    }
};
