<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('capstone_project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capstone_project_id')->constrained('capstone_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['capstone_project_id', 'user_id']);
        });

        // Backfill existing capstone projects with their uploader as initial member
        $existingProjects = DB::table('capstone_projects')
            ->select('id', 'user_id', 'created_at', 'updated_at')
            ->get();

        $now = now();
        foreach ($existingProjects as $project) {
            if ($project->user_id) {
                DB::table('capstone_project_user')->insertOrIgnore([
                    'capstone_project_id' => $project->id,
                    'user_id' => $project->user_id,
                    'created_at' => $project->created_at ?? $now,
                    'updated_at' => $project->updated_at ?? $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capstone_project_user');
    }
};
