<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * M15-06: Añade tipado y estado a la tabla EAV config_varias.
     */
    public function up(): void
    {
        Schema::table('config_varias', function (Blueprint $table) {
            if (! Schema::hasColumn('config_varias', 'tipo')) {
                $table->string('tipo', 30)->default('string')->after('descripcion');
            }
            if (! Schema::hasColumn('config_varias', 'estado')) {
                $table->boolean('estado')->default(true)->after('tipo');
            }
        });

        // Tipado semántico de configuraciones conocidas
        $tipos = [
            'max_tamano_archivo' => 'integer',
            'numeracion_unificada' => 'boolean',
            'multi_sede' => 'integer',
            'backups_automaticos' => 'boolean',
            'correo_password' => 'secret',
            'imap_password' => 'secret',
            'notificar_radicado_al_tercero' => 'boolean',
            'notificar_pqrs_al_tercero' => 'boolean',
        ];

        foreach ($tipos as $clave => $tipo) {
            DB::table('config_varias')
                ->where('clave', $clave)
                ->update(['tipo' => $tipo]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_varias', function (Blueprint $table) {
            if (Schema::hasColumn('config_varias', 'estado')) {
                $table->dropColumn('estado');
            }
            if (Schema::hasColumn('config_varias', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
