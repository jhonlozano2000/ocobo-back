<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Este script se ejecutará para comparar el rendimiento
        // No crea tablas, solo ejecuta las comparaciones

        Log::info('=== COMPARACIÓN DE RENDIMIENTO ===');

        // Habilitar el log de consultas para medir el rendimiento
        DB::enableQueryLog();

        // 1. Consulta original (simulada)
        $startTime = microtime(true);

        $originalQuery = DB::table('ventanilla_radica_reci as vrr')
            ->leftJoin('clasificacion_documental_trd as cd', 'vrr.clasifica_documen_id', '=', 'cd.id')
            ->leftJoin('clasificacion_documental_trd as cd_parent', 'cd.parent', '=', 'cd_parent.id')
            ->leftJoin('clasificacion_documental_trd as cd_grandparent', 'cd_parent.parent', '=', 'cd_grandparent.id')
            ->leftJoin('gestion_terceros as gt', 'vrr.tercero_id', '=', 'gt.id')
            ->leftJoin('config_listas_detalles as cld', 'vrr.medio_recep_id', '=', 'cld.id')
            ->leftJoin('config_server_archivos as csa', 'vrr.config_server_id', '=', 'csa.id')
            ->select([
                'vrr.id',
                'vrr.num_radicado',
                'vrr.created_at',
                'vrr.fec_venci',
                'vrr.archivo_digital',
                'vrr.asunto',
                'cd.cod as clasificacion_cod',
                'cd.nom as clasificacion_nom',
                'gt.nom_razo_soci as tercero_nombre',
                'cld.nombre as medio_recepcion_nombre'
            ])
            ->orderBy('vrr.created_at', 'desc')
            ->limit(100)
            ->get();

        $originalTime = microtime(true) - $startTime;
        $originalQueries = DB::getQueryLog();
        DB::flushQueryLog();

        // 2. Consulta optimizada con vista
        $startTime = microtime(true);

        $optimizedQuery = DB::table('ventanilla_radica_reci_optimized_view')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $optimizedTime = microtime(true) - $startTime;
        $optimizedQueries = DB::getQueryLog();

        // 3. Calcular mejora de rendimiento
        $improvement = (($originalTime - $optimizedTime) / $originalTime) * 100;

        // 4. Log de resultados
        Log::info('CONSULTA ORIGINAL:');
        Log::info("Tiempo de ejecución: {$originalTime} segundos");
        Log::info("Número de consultas: " . count($originalQueries));
        Log::info("Registros obtenidos: " . $originalQuery->count());

        Log::info('CONSULTA OPTIMIZADA:');
        Log::info("Tiempo de ejecución: {$optimizedTime} segundos");
        Log::info("Número de consultas: " . count($optimizedQueries));
        Log::info("Registros obtenidos: " . $optimizedQuery->count());

        Log::info('MEJORA DE RENDIMIENTO:');
        Log::info("Mejora de tiempo: {$improvement}%");
        Log::info("Reducción de consultas: " . (count($originalQueries) - count($optimizedQueries)));

        // 5. Mostrar consultas SQL ejecutadas
        Log::info('CONSULTAS ORIGINALES:');
        foreach ($originalQueries as $query) {
            Log::info("SQL: {$query['query']} | Tiempo: {$query['time']}ms");
        }

        Log::info('CONSULTAS OPTIMIZADAS:');
        foreach ($optimizedQueries as $query) {
            Log::info("SQL: {$query['query']} | Tiempo: {$query['time']}ms");
        }

        Log::info('=== FIN COMPARACIÓN DE RENDIMIENTO ===');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hay nada que revertir
    }
};
