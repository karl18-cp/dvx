<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('permission', 80);
            $table->boolean('allowed');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['role', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
