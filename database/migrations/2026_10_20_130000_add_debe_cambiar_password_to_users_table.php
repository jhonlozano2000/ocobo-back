<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contraseña temporal: mientras esté en true el usuario solo puede cambiarla (ISO 27001 A.9.2.4).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_password');
        });
    }
};
