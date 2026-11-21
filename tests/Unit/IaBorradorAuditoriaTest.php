<?php

namespace Tests\Unit;

use App\Events\Ia\BorradorIaGenerado;
use App\Jobs\Ia\ProyectarBorradorIaJob;
use App\Models\Ia\IaBorradorAuditoria;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias para el Job asíncrono y modelos de auditoría ISO 27001 (F2-03).
 */
class IaBorradorAuditoriaTest extends TestCase
{
    public function test_modelo_ia_borrador_auditoria_fillable_y_casts(): void
    {
        $modelo = new IaBorradorAuditoria;
        $fillable = $modelo->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('asunto_hash', $fillable);
        $this->assertContains('prompt_hash', $fillable);
        $this->assertContains('modelo_llm', $fillable);
        $this->assertContains('estado', $fillable);
        $this->assertContains('borrador_generado', $fillable);
        $this->assertContains('score_confianza', $fillable);
        $this->assertContains('citas_utilizadas', $fillable);

        $casts = $modelo->getCasts();
        $this->assertSame('float', $casts['score_confianza']);
        $this->assertSame('array', $casts['citas_utilizadas']);
        $this->assertSame('array', $casts['advertencias']);
        $this->assertSame('array', $casts['tokens_verificar']);
        $this->assertSame('boolean', $casts['fallback']);
    }

    public function test_evento_borrador_ia_generado_estructura_canal_y_nombre(): void
    {
        $userId = 42;
        $jobId = 'uuid-test-1234';
        $payload = [
            'success' => true,
            'estado' => 'OK',
            'borrador_respuesta' => 'Borrador generado institucional',
            'score_confianza' => 0.85,
            'citas' => ['[Prec.1] Radicado 20250101-001'],
            'advertencias' => [],
            'tokens_verificar' => [],
            'modelo_usado' => 'qwen2.5:7b-instruct-q4_K_M',
            'tiempo_total_ms' => 1250.0,
        ];

        $evento = new BorradorIaGenerado($userId, $jobId, $payload);

        $this->assertSame('private-ia-asistente.42', $evento->broadcastOn()->name);
        $this->assertSame('borrador.generado', $evento->broadcastAs());

        $data = $evento->broadcastWith();
        $this->assertSame('uuid-test-1234', $data['job_id']);
        $this->assertSame('OK', $data['estado']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('borrador_respuesta', $data);
    }

    public function test_job_proyectar_borrador_parametros_iniciales(): void
    {
        $userId = 15;
        $jobId = 'uuid-job-789';
        $datos = [
            'id_radicado' => 'RAD-2026-999',
            'asunto' => 'Solicitud historia clínica',
            'contenido' => 'Cuerpo de la petición',
            'tipo_solicitud' => 'Derecho de Petición',
            'flag_hc' => true,
        ];

        $job = new ProyectarBorradorIaJob($userId, $jobId, $datos, '127.0.0.1', 'Mozilla/5.0');

        $this->assertSame(15, $job->userId);
        $this->assertSame('uuid-job-789', $job->jobId);
        $this->assertSame('RAD-2026-999', $job->datos['id_radicado']);
        $this->assertTrue($job->datos['flag_hc']);
        $this->assertSame(300, $job->timeout);
        $this->assertSame(2, $job->tries);
    }
}
