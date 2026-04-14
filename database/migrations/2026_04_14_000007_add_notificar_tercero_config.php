<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('config_varias')->where('clave', 'notificar_radicado_al_tercero')->exists();
        if (!$existe) {
            DB::table('config_varias')->insert([
                'clave' => 'notificar_radicado_al_tercero',
                'valor' => 'false',
                'descripcion' => 'Enviar notificación al tercero cuando se radique un documento recibido',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('config_varias')->where('clave', 'notificar_radicado_al_tercero')->delete();
    }
};
