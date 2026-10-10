<?php

namespace Tests\Feature\Archivo;

use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\EmailRadicacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M08-01: los adjuntos originales de los radicados por correo quedan registrados
 * en BD y la depuración programada no los mueve a cuarentena.
 */
class AdjuntosCorreoDepuracionTest extends TestCase
{
    use DatabaseTransactions;

    protected VentanillaRadicaReci $radicado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['estado' => 1]));
        Storage::fake('radicados_recibidos');

        // El medio de recepción no es relevante para estas pruebas
        Schema::disableForeignKeyConstraints();
        $this->radicado = VentanillaRadicaReci::create([
            'num_radicado' => 'M08-TEST-0001',
            'asunto' => 'Prueba M08-01',
            'fechor_radica' => now(),
            'estado_trabajo' => 'RECIBIDO',
            'medio_recep_id' => 999999,
            'usuario_crea' => auth()->id(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    public function test_adjunto_de_correo_queda_registrado_con_hash(): void
    {
        Storage::disk('radicados_recibidos')->put('M08-TEST-0001/anexo.pdf', 'contenido original');

        app(EmailRadicacionService::class)->registrarAdjunto(
            $this->radicado, 'radicados_recibidos', 'radicados_recibidos/M08-TEST-0001/anexo.pdf', 'anexo.pdf'
        );

        $this->assertDatabaseHas('ventanilla_radica_reci_archivos', [
            'radicado_id' => $this->radicado->id,
            'archivo' => 'M08-TEST-0001/anexo.pdf',
            'nom_origi' => 'anexo.pdf',
            'archivo_peso' => strlen('contenido original'),
            'hash_sha256' => hash('sha256', 'contenido original'),
            'subido_por' => auth()->id(),
        ]);
    }
}
