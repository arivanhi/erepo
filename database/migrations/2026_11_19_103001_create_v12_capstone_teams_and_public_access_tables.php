<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capstone_teams', function (Blueprint $table) {
            $table->id();
            $table->string('group_code')->unique();
            $table->string('team_name')->nullable();
            $table->string('academic_year');
            $table->foreignId('dosen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('login_token_encrypted')->nullable();
            $table->timestamp('login_token_expires_at')->nullable();
            $table->timestamp('login_token_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('capstone_team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capstone_team_id')->constrained('capstone_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['capstone_team_id', 'user_id']);
        });

        Schema::table('capstone_projects', function (Blueprint $table) {
            $table->foreignId('capstone_team_id')->nullable()->after('group_id')->constrained('capstone_teams')->nullOnDelete();
            $table->text('acceptance_note')->nullable()->after('revision_note');
        });

        Schema::create('public_download_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // capstone_pdf | script_archive
            $table->foreignId('capstone_project_id')->nullable()->constrained('capstone_projects')->cascadeOnDelete();
            $table->foreignId('script_project_id')->nullable()->constrained('script_projects')->cascadeOnDelete();
            $table->string('requester_name')->nullable();
            $table->string('requester_email');
            $table->text('purpose')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->string('approval_token', 80)->nullable()->unique();
            $table->string('download_code', 24)->nullable();
            $table->string('download_url_token', 80)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->string('approved_by_email')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'requester_email']);
        });

        // Backfill existing Capstone groups into capstone_teams seamlessly
        $existingProjects = DB::table('capstone_projects')->orderBy('id')->get();
        $teamsByCode = [];
        $now = now();

        foreach ($existingProjects as $proj) {
            $code = strtoupper(trim((string) $proj->group_id));
            if ($code === '') {
                continue;
            }

            if (! isset($teamsByCode[$code])) {
                $teamId = DB::table('capstone_teams')->insertGetId([
                    'group_code' => $code,
                    'team_name' => $proj->title,
                    'academic_year' => $proj->academic_year ?: '2025-2026',
                    'dosen_id' => $proj->dosen_id,
                    'lead_user_id' => $proj->user_id,
                    'created_at' => $proj->created_at ?? $now,
                    'updated_at' => $proj->updated_at ?? $now,
                ]);
                $teamsByCode[$code] = $teamId;
            } else {
                $teamId = $teamsByCode[$code];
            }

            DB::table('capstone_projects')
                ->where('id', $proj->id)
                ->update(['capstone_team_id' => $teamId]);

            $memberIds = DB::table('capstone_project_user')
                ->where('capstone_project_id', $proj->id)
                ->pluck('user_id')
                ->all();

            if ($proj->user_id && ! in_array($proj->user_id, $memberIds, true)) {
                $memberIds[] = $proj->user_id;
            }

            foreach ($memberIds as $uid) {
                DB::table('capstone_team_user')->insertOrIgnore([
                    'capstone_team_id' => $teamId,
                    'user_id' => $uid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_download_requests');

        Schema::table('capstone_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('capstone_team_id');
            $table->dropColumn('acceptance_note');
        });

        Schema::dropIfExists('capstone_team_user');
        Schema::dropIfExists('capstone_teams');
    }
};
