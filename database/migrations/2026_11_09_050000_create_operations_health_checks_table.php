<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('service_key', 60)->unique();
            $table->string('status', 20)->default('healthy');
            $table->json('details')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_health_checks');
    }
};
