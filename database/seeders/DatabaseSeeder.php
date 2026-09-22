<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'username' => 'DVX001',
        ], [
            'name' => 'DVX Administrator',
            'email' => 'dvx001@divertex.local',
            'role' => 'admin',
            'password' => 'Admin2026',
            'email_verified_at' => now(),
        ]);
    }
}
