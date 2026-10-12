<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M15-04: la contraseña del servidor de archivos se guardaba con bcrypt (irreversible) y nunca
 * podría usarse para conectarse. Pasa a cifrado reversible (cast `encrypted`), que no cabe en
 * VARCHAR(255). Los hashes existentes no se pueden recuperar: se vacían y hay que volver a
 * capturar la contraseña de cada servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_server_archivos', function (Blueprint $table) {
            $table->text('password')->nullable()->change();
        });

        DB::table('config_server_archivos')->update(['password' => null]);
    }

    public function down(): void
    {
        DB::table('config_server_archivos')->update(['password' => '']);

        Schema::table('config_server_archivos', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
