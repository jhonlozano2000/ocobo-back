<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M05-02: cifra la contraseña de correo que haya quedado guardada en texto plano
 * (antes solo se cifraba al guardar desde ConfigVarias::actualizarConfiguracion).
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('config_varias')->where('clave', 'correo_password')->first();

        if (! $row || blank($row->valor)) {
            return;
        }

        try {
            decrypt($row->valor);

            return; // ya está cifrada
        } catch (DecryptException) {
            DB::table('config_varias')
                ->where('id', $row->id)
                ->update(['valor' => encrypt((string) $row->valor), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: no se vuelve a guardar la contraseña en texto plano.
    }
};
