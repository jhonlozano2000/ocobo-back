<?php

namespace App\Console\Commands\Ia;

use App\Services\Ia\IaAprendizajeContinuoService;
use Illuminate\Console\Command;

/**
 * Comando Artisan para la indexación RAG incremental de respuestas institucionales aprobadas (HITL F4-02).
 *
 * Selecciona los borradores con edit_ratio calificado (candidatos SFT / edición moderada),
 * los envía al servicio de IA local (:8002) para su anonimización Ley 1581 e indexación en SQLite FTS5 y FAISS,
 * y actualiza la metadata inmutable en la tabla ia_borradores_auditoria.
 */
class IndexarRespuestasAprobadasCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'ia:indexar-respuestas-aprobadas
                            {--limit=50 : Cantidad máxima de documentos a procesar en el lote}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Indexa de forma incremental en el RAG local las respuestas aprobadas por funcionarios humanos (HITL F4-02)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(IaAprendizajeContinuoService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $this->info('=== OCOBO SGDEA: Indexación RAG Incremental HITL (F4-02) ===');
        $this->line("Buscando respuestas firmadas con edit_ratio aprobado (límite: {$limit})...");

        $resultado = $service->indexarRespuestasAprobadas($limit);

        if (! ($resultado['success'] ?? false)) {
            $this->error('Error: '.($resultado['mensaje'] ?? 'Fallo desconocido en la indexación'));
            if (! empty($resultado['error'])) {
                $this->line('Detalle: '.$resultado['error']);
            }

            return self::FAILURE;
        }

        $enviados = $resultado['total_enviados'] ?? 0;
        if ($enviados === 0) {
            $this->info('No hay respuestas pendientes de indexación incremental.');

            return self::SUCCESS;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Documentos Procesados', $enviados],
                ['Chunks Generados e Indexados', $resultado['chunks_generados'] ?? 0],
                ['Entidades Anonimizadas (Ley 1581)', $resultado['entidades_anonimizadas'] ?? 0],
                ['Total Vectores FAISS en Índice', $resultado['total_vectores_faiss'] ?? 0],
                ['Tiempo Total de Inferencia', ($resultado['tiempo_total_s'] ?? 0.0).' s'],
            ]
        );

        $this->info('✓ '.($resultado['mensaje'] ?? 'Lote indexado exitosamente.'));

        return self::SUCCESS;
    }
}
