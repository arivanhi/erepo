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
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'mahasiswa', 'dosen', 'kaprodi', 'koordinator_ta') NOT NULL DEFAULT 'mahasiswa'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('mahasiswa')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::table('users')->where('role', 'koordinator_ta')->update(['role' => 'dosen']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'mahasiswa', 'dosen', 'kaprodi') NOT NULL DEFAULT 'mahasiswa'");
        }
    }
};
