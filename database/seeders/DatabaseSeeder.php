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
        $admin = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $mahasiswa = User::factory()->create([
            'name' => 'Mahasiswa User',
            'email' => 'mahasiswa@example.com',
            'role' => 'mahasiswa',
        ]);

        $dosen = User::factory()->create([
            'name' => 'Dosen User',
            'email' => 'dosen@example.com',
            'role' => 'dosen',
        ]);

        $kaprodi = User::factory()->create([
            'name' => 'Kaprodi User',
            'email' => 'kaprodi@example.com',
            'role' => 'kaprodi',
        ]);

        // Seed Capstone Projects
        \App\Models\CapstoneProject::create([
            'title' => 'AI Driven Knowledge Base',
            'academic_year' => '2024-2025',
            'group_id' => 'GRP-110',
            'status' => 'pending',
            'user_id' => $mahasiswa->id,
        ]);
        \App\Models\CapstoneProject::create([
            'title' => 'Machine Learning in Quantum Computing',
            'academic_year' => '2023-2024',
            'group_id' => 'GRP-099',
            'status' => 'approved',
            'user_id' => $mahasiswa->id,
        ]);

        // Seed Script Projects
        \App\Models\ScriptProject::create([
            'title' => 'Smart Irrigation System',
            'academic_year' => '2023-2024',
            'group_id' => 'GRP-742',
            'status' => 'pending',
            'user_id' => $mahasiswa->id,
        ]);
        \App\Models\ScriptProject::create([
            'title' => 'Data Processing Pipeline',
            'academic_year' => '2023-2024',
            'group_id' => 'GRP-743',
            'status' => 'approved',
            'user_id' => $mahasiswa->id,
        ]);
    }
}
