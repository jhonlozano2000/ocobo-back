<?php

use App\Services\OfiArchivo\ConfiguracionArchivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajustes del módulo de expedientes que cada entidad administra en Otras configuraciones.
     * Arrancan con el valor que hoy tenga el entorno para no cambiar su comportamiento.
     */
    public function up(): void
    {
        $configuraciones = [
            [
                'clave' => ConfiguracionArchivo::FOLIOS_POR_TOMO,
                'valor' => (string) max(1, (int) config('archivo.folios_por_tomo', 200)),
                'descripcion' => 'Folios máximos por tomo del expediente físico; el documento que no cabe abre el tomo siguiente',
                'tipo' => 'integer',
            ],
            [
                'clave' => ConfiguracionArchivo::MESES_INACTIVIDAD,
                'valor' => (string) max(1, (int) config('archivo.meses_inactividad', 6)),
                'descripcion' => 'Meses sin incorporaciones tras los cuales se avisa que un expediente abierto está inactivo',
                'tipo' => 'integer',
            ],
            [
                'clave' => ConfiguracionArchivo::INDICE_TSA,
                'valor' => config('firma.tsa_indice_expediente') ? 'true' : 'false',
                'descripcion' => 'Sellar con la autoridad de estampado cronológico cada versión firmada del índice electrónico',
                'tipo' => 'boolean',
            ],
        ];

        foreach ($configuraciones as $config) {
            if (! DB::table('config_varias')->where('clave', $config['clave'])->exists()) {
                DB::table('config_varias')->insert($config + ['estado' => true]);
            }
        }
    }

    public function down(): void
    {
        DB::table('config_varias')->whereIn('clave', [
            ConfiguracionArchivo::FOLIOS_POR_TOMO,
            ConfiguracionArchivo::MESES_INACTIVIDAD,
            ConfiguracionArchivo::INDICE_TSA,
        ])->delete();
    }
};
