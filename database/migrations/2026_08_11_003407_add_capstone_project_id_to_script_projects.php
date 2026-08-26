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
        Schema::table('script_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('capstone_project_id')->nullable()->after('user_id');
            $table->foreign('capstone_project_id')->references('id')->on('capstone_projects')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('script_projects', function (Blueprint $table) {
            $table->dropForeign(['capstone_project_id']);
            $table->dropColumn('capstone_project_id');
        });
    }
};
