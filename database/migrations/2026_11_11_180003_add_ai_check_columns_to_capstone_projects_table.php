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
            $table->string('ai_check_status')->nullable()->after('status');
            $table->json('ai_check_result')->nullable()->after('ai_check_status');
            $table->timestamp('ai_checked_at')->nullable()->after('ai_check_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capstone_projects', function (Blueprint $table) {
            $table->dropColumn(['ai_check_status', 'ai_check_result', 'ai_checked_at']);
        });
    }
};
