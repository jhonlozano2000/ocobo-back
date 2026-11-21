<?php

namespace App\Jobs\Ia;

use App\Events\Ia\BorradorIaGenerado;
use App\Models\Ia\IaBorradorAuditoria;
use App\Models\User;
use App\Services\Ia\IaAsistenteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job asíncrono para proyección de borradores de IA en segundo plano (F2-03).
 *
 * Evita bloqueos de conexiones HTTP durante la inferencia en CPU (100–140 s),
 * registra la auditoría inmutable ISO 27001 y notifica al usuario vía Laravel Reverb.
 */
class ProyectarBorradorIaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos permitidos.
     */
    public int $tries = 2;

    /**
     * Tiempo límite de ejecución en segundos (máximo 300 s).
     */
    public int $timeout = 300;

    /**
     * @param  int  $userId  Usuario que solicita la proyección
     * @param  string  $jobId  UUID identificador de la tarea
     * @param  array  $datos  Parámetros validados de la solicitud
     * @param  string|null  $ipAddress  Dirección IP del cliente para auditoría
     * @param  string|null  $userAgent  User Agent del cliente para auditoría
     */
    public function __construct(
        public int $userId,
        public string $jobId,
        public array $datos,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {}

    /**
     * Ejecuta el trabajo de inferencia y emisión de resultados.
     */
    public function handle(IaAsistenteService $iaService): void
    {
        $user = User::find($this->userId);
        $tInicio = microtime(true);

        try {
            $resultado = $iaService->proyectarBorrador($this->datos, $user);

            $asunto = (string) ($this->datos['asunto'] ?? '');
            $contenido = (string) ($this->datos['contenido'] ?? '');
            $asuntoHash = hash('sha256', $asunto);
            $promptHash = hash('sha256', $asunto.'|'.$contenido.'|'.json_encode($resultado['citas'] ?? []));

            $auditoria = IaBorradorAuditoria::create([
                'user_id' => $this->userId,
                'id_radicado' => $this->datos['id_radicado'] ?? null,
                'tipo_radicado' => $this->datos['tipo_solicitud'] ?? 'Derecho de Petición',
                'asunto_hash' => $asuntoHash,
                'prompt_hash' => $promptHash,
                'modelo_llm' => $resultado['modelo_usado'] ?? config('services.ia.model', 'qwen2.5:7b-instruct-q4_K_M'),
                'estado' => $resultado['estado'] ?? 'OK',
                'score_confianza' => $resultado['score_confianza'] ?? 0.0,
                'citas_utilizadas' => $resultado['citas'] ?? [],
                'advertencias' => $resultado['advertencias'] ?? [],
                'tokens_verificar' => $resultado['tokens_verificar'] ?? [],
                'borrador_generado' => $resultado['borrador_respuesta'] ?? '',
                'tiempo_retrieval_ms' => $resultado['tiempo_retrieval_ms'] ?? 0.0,
                'tiempo_reranking_ms' => $resultado['tiempo_reranking_ms'] ?? 0.0,
                'tiempo_llm_ms' => $resultado['tiempo_llm_ms'] ?? 0.0,
                'tiempo_total_ms' => $resultado['tiempo_total_ms'] ?? (round((microtime(true) - $tInicio) * 1000, 2)),
                'fallback' => (bool) ($resultado['fallback'] ?? false),
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
            ]);

            $payload = array_merge($resultado, [
                'auditoria_id' => $auditoria->id,
            ]);

            BorradorIaGenerado::dispatch($this->userId, $this->jobId, $payload);

            Log::info("Proyección IA completada con éxito para usuario {$this->userId}", [
                'job_id' => $this->jobId,
                'auditoria_id' => $auditoria->id,
                'estado' => $auditoria->estado,
            ]);
        } catch (\Throwable $e) {
            Log::error("Fallo crítico en ProyectarBorradorIaJob: {$e->getMessage()}", [
                'job_id' => $this->jobId,
                'user_id' => $this->userId,
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $asuntoHash = hash('sha256', (string) ($this->datos['asunto'] ?? ''));
                $promptHash = hash('sha256', (string) ($this->datos['asunto'] ?? '').'|ERROR');

                IaBorradorAuditoria::create([
                    'user_id' => $this->userId,
                    'id_radicado' => $this->datos['id_radicado'] ?? null,
                    'tipo_radicado' => $this->datos['tipo_solicitud'] ?? 'Derecho de Petición',
                    'asunto_hash' => $asuntoHash,
                    'prompt_hash' => $promptHash,
                    'modelo_llm' => 'ERROR',
                    'estado' => 'ERROR',
                    'score_confianza' => 0.0,
                    'citas_utilizadas' => [],
                    'advertencias' => [],
                    'tokens_verificar' => [],
                    'borrador_generado' => '',
                    'tiempo_total_ms' => round((microtime(true) - $tInicio) * 1000, 2),
                    'fallback' => false,
                    'ip_address' => $this->ipAddress,
                    'user_agent' => $this->userAgent,
                ]);
            } catch (\Throwable $auditoriaEx) {
                Log::error("Error registrando auditoría de fallo IA: {$auditoriaEx->getMessage()}");
            }

            BorradorIaGenerado::dispatch($this->userId, $this->jobId, [
                'success' => false,
                'estado' => 'ERROR',
                'borrador_respuesta' => '',
                'citas' => [],
                'advertencias' => [],
                'tokens_verificar' => [],
                'score_confianza' => 0.0,
                'modelo_usado' => 'N/A',
                'tiempo_total_ms' => round((microtime(true) - $tInicio) * 1000, 2),
                'error' => 'Ocurrió un error en el procesamiento en segundo plano del borrador.',
            ]);
        }
    }
}
