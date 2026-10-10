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

    private function archivoAntiguo(string $ruta, int $dias): void
    {
        $disco = Storage::disk('radicados_recibidos');
        $disco->put($ruta, 'x');
        touch($disco->path($ruta), now()->subDays($dias)->getTimestamp());
    }

    public function test_depuracion_no_aparta_archivos_de_carpetas_de_radicados(): void
    {
        $this->archivoAntiguo('M08-TEST-0001/adjunto-sin-registro.pdf', 90);
        $this->archivoAntiguo('huerfano-antiguo.pdf', 90);
        $this->archivoAntiguo('huerfano-reciente.pdf', 5);

        $this->artisan('archivo:depurar-digital', ['--disco' => 'radicados_recibidos', '--dias' => 30])->assertSuccessful();

        $disco = Storage::disk('radicados_recibidos');
        $disco->assertExists('M08-TEST-0001/adjunto-sin-registro.pdf');
        $disco->assertExists('huerfano-reciente.pdf');
        $disco->assertMissing('huerfano-antiguo.pdf');
        $disco->assertExists('cuarentena/'.now()->format('Y-m-d').'/huerfano-antiguo.pdf');
    }

    public function test_depuracion_en_dry_run_no_mueve_nada(): void
    {
        $this->archivoAntiguo('huerfano-antiguo.pdf', 90);

        $this->artisan('archivo:depurar-digital', ['--disco' => 'radicados_recibidos', '--dias' => 30, '--dry-run' => true])->assertSuccessful();

        Storage::disk('radicados_recibidos')->assertExists('huerfano-antiguo.pdf');
    }

    public function test_conciliacion_restaura_de_cuarentena_y_registra_adjuntos(): void
    {
        $disco = Storage::disk('radicados_recibidos');
        $disco->put('M08-TEST-0001/correo-M08-TEST-0001.pdf', 'pdf del correo');
        $disco->put('M08-TEST-0001/factura.xlsx', 'adjunto en su sitio');
        $disco->put('cuarentena/2026-09-27/M08-TEST-0001/anexo.pdf', 'adjunto apartado');
        $this->radicado->update(['archivo_digital' => 'M08-TEST-0001/correo-M08-TEST-0001.pdf']);

        $this->artisan('archivo:conciliar-adjuntos-correo', ['--restaurar-cuarentena' => true])->assertSuccessful();

        $disco->assertExists('M08-TEST-0001/anexo.pdf');
        $disco->assertMissing('cuarentena/2026-09-27/M08-TEST-0001/anexo.pdf');

        $archivos = \DB::table('ventanilla_radica_reci_archivos')->where('radicado_id', $this->radicado->id)->pluck('archivo')->sort()->values()->all();
        $this->assertSame(['M08-TEST-0001/anexo.pdf', 'M08-TEST-0001/factura.xlsx'], $archivos);

        // Idempotente
        $this->artisan('archivo:conciliar-adjuntos-correo')->assertSuccessful();
        $this->assertSame(2, \DB::table('ventanilla_radica_reci_archivos')->where('radicado_id', $this->radicado->id)->count());
    }
}
