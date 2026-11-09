<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_updates', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('body');
            $table->string('author_name', 150);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('public_update_comments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('public_update_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email', 254)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_update_comments');
        Schema::dropIfExists('public_updates');
    }
};
