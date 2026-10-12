<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REP-002: cada reporte programado tiene dueño; se ejecuta con su permiso y alcance,
 * y solo él lo ve, edita o elimina. Los existentes quedan sin dueño y el scheduler los omite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes_programados', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reportes_programados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
