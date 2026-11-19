<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('capstone_projects', function (Blueprint $table) {
            $table->text('revision_note')->nullable()->after('ai_checked_at');
            $table->foreignId('reviewed_by')->nullable()->after('revision_note')->constrained('users')->nullOnDelete();
            $table->string('reviewer_role')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewer_role');
        });

        Schema::table('script_projects', function (Blueprint $table) {
            $table->text('revision_note')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('revision_note')->constrained('users')->nullOnDelete();
            $table->string('reviewer_role')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewer_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capstone_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['revision_note', 'reviewer_role', 'reviewed_at']);
        });

        Schema::table('script_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['revision_note', 'reviewer_role', 'reviewed_at']);
        });
    }
};
