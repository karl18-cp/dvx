<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->string('name', 80)->nullable()->after('type');
            $table->foreignId('created_by')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });
        Schema::create('chat_conversation_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversation_members');
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('name');
        });
    }
};
