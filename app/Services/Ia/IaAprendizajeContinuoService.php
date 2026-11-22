<?php

namespace App\Services\Ia;

use App\Models\Ia\IaBorradorAuditoria;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Aprendizaje Continuo Human-in-the-Loop (HITL) para el Pipeline de IA OCOBO.
 *
 * Responsabilidades:
 * - Captura determinística de diferencias (diff) entre el borrador original de la IA y el documento final firmado.
 * - Cálculo normalizado de distancia de edición (edit_ratio) para medir la adherencia del funcionario al borrador.
 * - Clasificación de calidad (candidato SFT / Replay Buffer vs descartado por reescritura sustancial)
 *   para prevenir envenenamiento de datos (data poisoning) y olvido catastrófico (catastrophic forgetting).
 */
class IaAprendizajeContinuoService
{
    /** Umbral máximo de edit_ratio para considerar un documento como candidato SFT / Replay Buffer (≤ 40% editado) */
    public const UMBRAL_CANDIDATO_SFT = 0.4000;

    /** Umbral máximo de edit_ratio para aceptar un documento como precedente moderado (≤ 70% editado) */
    public const UMBRAL_EDICION_MODERADA = 0.7000;

    /**
     * Captura el diff y actualiza las métricas de aprendizaje de un documento firmado o radicado.
     *
     * @param  Documento  $documento  Documento colaborativo firmado
     * @param  string|null  $radicadoSalida  Número de radicado oficial si ya fue radicado
     * @param  User|null  $user  Usuario firmante o radicador
     * @return IaBorradorAuditoria|null Registro de auditoría actualizado o null si no aplica
     */
    public function capturarDiffDocumento(
        Documento $documento,
        ?string $radicadoSalida = null,
        ?User $user = null
    ): ?IaBorradorAuditoria {
        // Buscar la auditoría de IA ligada a este documento colaborativo
        $auditoria = IaBorradorAuditoria::where('radicado_id', $documento->id)
            ->orWhere('id_radicado', 'DOC-'.$documento->id)
            ->latest('id')
            ->first();

        if (! $auditoria) {
            // El documento fue redactado manualmente sin asistencia de IA
            return null;
        }

        if (empty($auditoria->borrador_generado)) {
            Log::info('Auditoría IA sin borrador registrado, se omite cálculo de edit_ratio', [
                'auditoria_id' => $auditoria->id,
                'documento_id' => $documento->id,
            ]);

            return null;
        }

        $contenidoDoc = Contenido::where('documento_id', $documento->id)->first();
        if (! $contenidoDoc || empty($contenidoDoc->contenido_yjs)) {
            return null;
        }

        $textoFinal = $this->extraerTextoPlanoDeTipTap($contenidoDoc->contenido_yjs);
        if (empty(trim($textoFinal))) {
            return null;
        }

        $diffStats = $this->calcularEditRatio($auditoria->borrador_generado, $textoFinal);

        $metadata = array_merge(
            $auditoria->metadata_aprendizaje ?? [],
            $diffStats['metadata'],
            [
                'documento_id' => $documento->id,
                'documento_titulo' => $documento->titulo,
                'user_firmante_id' => $user?->id,
                'capturado_en' => now()->toIso8601String(),
            ]
        );

        $auditoria->update([
            'contenido_final' => $textoFinal,
            'edit_ratio' => $diffStats['edit_ratio'],
            'estado_aprendizaje' => $diffStats['estado_aprendizaje'],
            'firmado_at' => $auditoria->firmado_at ?? now(),
            'radicado_salida' => $radicadoSalida ?? $auditoria->radicado_salida,
            'metadata_aprendizaje' => $metadata,
        ]);

        Log::info('Diff de aprendizaje continuo capturado para documento IA', [
            'auditoria_id' => $auditoria->id,
            'documento_id' => $documento->id,
            'edit_ratio' => $diffStats['edit_ratio'],
            'estado_aprendizaje' => $diffStats['estado_aprendizaje'],
            'radicado_salida' => $radicadoSalida,
        ]);

        return $auditoria->fresh();
    }

    /**
     * Calcula la distancia de edición normalizada (edit_ratio) entre el borrador IA y el texto final.
     *
     * @param  string  $borradorIa  Texto generado inicialmente por el motor de IA
     * @param  string  $contenidoFinal  Texto final aprobado y firmado por el funcionario
     * @return array{edit_ratio: float, similar_ratio: float, estado_aprendizaje: string, metadata: array<string, mixed>}
     */
    public function calcularEditRatio(string $borradorIa, string $contenidoFinal): array
    {
        $borradorNorm = $this->normalizarTexto($borradorIa);
        $finalNorm = $this->normalizarTexto($contenidoFinal);

        $lenBorrador = mb_strlen($borradorNorm);
        $lenFinal = mb_strlen($finalNorm);

        if ($lenBorrador === 0 && $lenFinal === 0) {
            return [
                'edit_ratio' => 0.0000,
                'similar_ratio' => 1.0000,
                'estado_aprendizaje' => 'descartado_vacio',
                'metadata' => [
                    'clasificacion' => 'Textos vacíos',
                    'palabras_borrador' => 0,
                    'palabras_final' => 0,
                ],
            ];
        }

        if ($borradorNorm === $finalNorm) {
            $palabras = count($this->tokenizarPalabras($borradorNorm));

            return [
                'edit_ratio' => 0.0000,
                'similar_ratio' => 1.0000,
                'estado_aprendizaje' => 'capturado_candidato_sft',
                'metadata' => [
                    'clasificacion' => 'Aprobado sin modificaciones (100% adherencia)',
                    'palabras_borrador' => $palabras,
                    'palabras_final' => $palabras,
                    'caracteres_borrador' => $lenBorrador,
                    'caracteres_final' => $lenFinal,
                ],
            ];
        }

        // Algoritmo de similitud Oliver (1993) / difflib
        similar_text($borradorNorm, $finalNorm, $porcentajeSimilitud);
        $similarRatio = $porcentajeSimilitud / 100.0;
        $editRatio = round(max(0.0, min(1.0, 1.0 - $similarRatio)), 4);

        $tokensBorrador = $this->tokenizarPalabras($borradorNorm);
        $tokensFinal = $this->tokenizarPalabras($finalNorm);

        $palabrasBorrador = count($tokensBorrador);
        $palabrasFinal = count($tokensFinal);

        // Clasificación estricta HITL
        if ($editRatio <= self::UMBRAL_CANDIDATO_SFT) {
            $estadoAprendizaje = 'capturado_candidato_sft';
            $clasificacion = 'Aprobado con edición menor (Candidato SFT / Replay Buffer)';
        } elseif ($editRatio <= self::UMBRAL_EDICION_MODERADA) {
            $estadoAprendizaje = 'capturado_edicion_moderada';
            $clasificacion = 'Aprobado con edición moderada (Supervisión RAG)';
        } else {
            $estadoAprendizaje = 'descartado_alta_edicion';
            $clasificacion = 'Reescritura sustancial por funcionario (Descartado para reentrenamiento)';
        }

        return [
            'edit_ratio' => $editRatio,
            'similar_ratio' => round($similarRatio, 4),
            'estado_aprendizaje' => $estadoAprendizaje,
            'metadata' => [
                'clasificacion' => $clasificacion,
                'palabras_borrador' => $palabrasBorrador,
                'palabras_final' => $palabrasFinal,
                'caracteres_borrador' => $lenBorrador,
                'caracteres_final' => $lenFinal,
                'adherencia_porcentaje' => round($similarRatio * 100, 2),
                'calculado_en' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Extrae texto plano continuo desde la estructura TipTap / Yjs almacenada en JSON.
     *
     * @param  array<string, mixed>  $contenido
     */
    public function extraerTextoPlanoDeTipTap(array $contenido): string
    {
        $bloques = isset($contenido['content']) && is_array($contenido['content'])
            ? $contenido['content']
            : $contenido;

        $texto = '';
        foreach ($bloques as $bloque) {
            if (! is_array($bloque) || ! isset($bloque['type'])) {
                continue;
            }

            match ($bloque['type']) {
                'doc' => $texto .= $this->extraerTextoPlanoDeTipTap($bloque['content'] ?? []),
                'paragraph' => $texto .= $this->extraerTextoInline($bloque['content'] ?? [])."\n",
                'heading' => $texto .= $this->extraerTextoInline($bloque['content'] ?? [])."\n\n",
                'bulletList', 'orderedList' => $texto .= $this->extraerTextoLista($bloque)."\n",
                'blockquote' => $texto .= $this->extraerTextoInline($bloque['content'] ?? [])."\n\n",
                'table' => $texto .= $this->extraerTextoTabla($bloque)."\n",
                default => null,
            };
        }

        return trim($texto);
    }

    /**
     * Extrae texto plano de nodos inline (text, marks).
     */
    protected function extraerTextoInline(array $content): string
    {
        $texto = '';
        foreach ($content as $inline) {
            if (isset($inline['text'])) {
                $texto .= $inline['text'];
            }
        }

        return $texto;
    }

    /**
     * Extrae texto plano de elementos de lista (bulletList / orderedList).
     */
    protected function extraerTextoLista(array $bloque): string
    {
        $texto = '';
        foreach ($bloque['content'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'listItem') {
                $texto .= '- '.$this->extraerTextoPlanoDeTipTap($item['content'] ?? [])."\n";
            }
        }

        return $texto;
    }

    /**
     * Extrae texto plano de tablas TipTap.
     */
    protected function extraerTextoTabla(array $bloque): string
    {
        $filas = [];
        foreach ($bloque['content'] ?? [] as $fila) {
            if (($fila['type'] ?? '') === 'tableRow') {
                $celdas = [];
                foreach ($fila['content'] ?? [] as $celda) {
                    $celdas[] = $this->extraerTextoPlanoDeTipTap($celda['content'] ?? []);
                }
                $filas[] = implode(' | ', $celdas);
            }
        }

        return implode("\n", $filas);
    }

    /**
     * Normaliza un texto para comparación determinística de diff (espacios, saltos, puntuación).
     */
    public function normalizarTexto(string $texto): string
    {
        // Reemplazar saltos de línea Windows por Unix
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        // Quitar etiquetas HTML residuales
        $texto = strip_tags($texto);
        // Colapsar espacios múltiples horizontales
        $texto = preg_replace('/[ \t]+/', ' ', $texto) ?? $texto;
        // Colapsar más de 2 saltos consecutivos
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * Tokeniza el texto en palabras eliminando espacios vacíos.
     *
     * @return array<int, string>
     */
    protected function tokenizarPalabras(string $texto): array
    {
        $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($palabras) ? $palabras : [];
    }

    /**
     * Obtiene estadísticas y métricas consolidadas del pipeline de aprendizaje continuo.
     *
     * @return array<string, mixed>
     */
    public function obtenerMetricasAprendizaje(): array
    {
        $totalCapturados = IaBorradorAuditoria::conDiffCapturado()->count();

        if ($totalCapturados === 0) {
            return [
                'total_capturados' => 0,
                'candidatos_sft' => 0,
                'edicion_moderada' => 0,
                'descartados_alta_edicion' => 0,
                'edit_ratio_promedio' => null,
                'adherencia_promedio_porcentaje' => null,
                'porcentaje_candidatos_sft' => 0.0,
                'distribucion_tipologias' => [],
            ];
        }

        $candidatosSft = IaBorradorAuditoria::candidatosSft()->count();
        $edicionModerada = IaBorradorAuditoria::where('estado_aprendizaje', 'capturado_edicion_moderada')->count();
        $descartados = IaBorradorAuditoria::where('estado_aprendizaje', 'descartado_alta_edicion')->count();

        $editRatioPromedio = (float) IaBorradorAuditoria::conDiffCapturado()->avg('edit_ratio');
        $adherenciaPromedio = round((1.0 - $editRatioPromedio) * 100, 2);

        $distribucion = IaBorradorAuditoria::conDiffCapturado()
            ->selectRaw('tipo_radicado, COUNT(*) as total, AVG(edit_ratio) as edit_ratio_promedio')
            ->groupBy('tipo_radicado')
            ->get()
            ->map(fn ($item) => [
                'tipo' => $item->tipo_radicado,
                'total' => (int) $item->total,
                'edit_ratio_promedio' => round((float) $item->edit_ratio_promedio, 4),
            ])
            ->toArray();

        return [
            'total_capturados' => $totalCapturados,
            'candidatos_sft' => $candidatosSft,
            'edicion_moderada' => $edicionModerada,
            'descartados_alta_edicion' => $descartados,
            'edit_ratio_promedio' => round($editRatioPromedio, 4),
            'adherencia_promedio_porcentaje' => $adherenciaPromedio,
            'porcentaje_candidatos_sft' => round(($candidatosSft / $totalCapturados) * 100, 2),
            'distribucion_tipologias' => $distribucion,
        ];
    }
}
