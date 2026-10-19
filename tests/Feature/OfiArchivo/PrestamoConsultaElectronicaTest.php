<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use App\Services\OfiArchivo\PrestamosService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Préstamo electrónico (diseño de expedientes §3.10): el solicitante consulta los documentos
 * mientras el préstamo está vigente, cada consulta queda registrada, la versión pública limita los
 * documentos y los clasificados o reservados no se descargan.
 */
class PrestamoConsultaElectronicaTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Gestion de Archivo -> Prestamos -> ';

    private User $archivista;

    private User $solicitante;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archivo_expedientes');

        [$this->archivista, $this->solicitante, $this->otro] = User::factory()->count(3)->create(['estado' => 1])->all();
        foreach (['Ver', 'Aprobar', 'Devolver', 'Solicitar'] as $accion) {
            $this->archivista->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
        }
        $this->archivista->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver Todos', 'web'));
        $this->archivista->givePermissionTo(Permission::findOrCreate(PrestamosService::PERMISO_VER_RESERVADOS, 'web'));
        foreach ([$this->solicitante, $this->otro] as $user) {
            $user->givePermissionTo(Permission::findOrCreate(self::PERM.'Solicitar', 'web'));
        }
    }

    /** Expediente electrónico abierto con un PDF, una imagen, un .docx y un folio sin archivo. */
    private function expediente(string $nivel = 'publico'): array
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'PE-'.uniqid(),
            'nombre_expediente' => 'Expediente Consulta Electronica',
            'anio' => 2026,
            'dependencia_id' => 1,
            'serie_trd_id' => 1,
            'estado' => 'abierto',
            'fase' => 'gestion',
            'soporte' => 'electronico',
            'nivel_acceso' => $nivel,
            'fundamento_acceso' => $nivel === 'publico' ? null : 'Ley 1712 art. 19',
            'fecha_apertura' => now()->subMonth(),
            'usuario_apertura_id' => $this->archivista->id,
        ]);
        Schema::enableForeignKeyConstraints();

        $docs = [];
        foreach (['acta.pdf', 'plano.png', 'informe.docx', null] as $orden => $nombre) {
            $path = $nombre ? "exp{$id}/".uniqid().'_'.$nombre : null;
            if ($path) {
                Storage::disk('archivo_expedientes')->put($path, 'contenido '.$nombre);
            }
            $docs[] = OfiArchivoExpedienteDocumento::create([
                'expediente_id' => $id,
                'tipo' => 'tipo_documental',
                'tipo_documental' => 'Acta',
                'orden' => $orden + 1,
                'fecha_documento' => now()->toDateString(),
                'asunto' => 'Documento '.($orden + 1),
                'archivo_path' => $path,
                'nombre_original' => $nombre,
                'detalle' => $nombre ?? 'Folio físico',
                'usuario_id' => $this->archivista->id,
            ])->id;
        }

        return [$id, $docs];
    }

    private function prestar(int $expedienteId, array $aprobacion = []): int
    {
        $id = $this->actingAs($this->solicitante)->postJson('/api/archivo/prestamos', [
            'expediente_id' => $expedienteId,
            'modalidad' => 'electronico',
            'motivo' => 'Consulta para auditoría',
            'fecha_devolucion_esperada' => now()->addDays(3)->toDateString(),
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar", $aprobacion)
            ->assertOk()->assertJsonPath('data.estado', 'prestado');

        return $id;
    }

    public function test_el_solicitante_consulta_y_descarga_y_queda_la_traza(): void
    {
        [$expId, [$pdf, $png, $docx, $sinArchivo]] = $this->expediente();
        $id = $this->prestar($expId);

        $documentos = collect($this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$id}/consulta")
            ->assertOk()->json('data.documentos'))->keyBy('id');
        $this->assertCount(4, $documentos);
        $this->assertTrue($documentos[$pdf]['visible']);
        $this->assertTrue($documentos[$pdf]['descargable']);
        $this->assertFalse($documentos[$docx]['visible']);
        $this->assertFalse($documentos[$sinArchivo]['disponible']);

        $respuesta = $this->actingAs($this->solicitante)->get("/api/archivo/prestamos/{$id}/documentos/{$pdf}");
        $respuesta->assertOk();
        $this->assertStringStartsWith('inline', $respuesta->headers->get('Content-Disposition'));
        $this->assertSame('contenido acta.pdf', $respuesta->streamedContent());

        $descarga = $this->actingAs($this->solicitante)->get("/api/archivo/prestamos/{$id}/documentos/{$docx}?accion=descargar");
        $descarga->assertOk();
        $this->assertStringStartsWith('attachment', $descarga->headers->get('Content-Disposition'));

        // Un .docx no se ve en el navegador y un folio sin archivo no se abre
        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$id}/documentos/{$docx}")->assertUnprocessable();
        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$id}/documentos/{$sinArchivo}")->assertUnprocessable();

        $this->assertSame(
            [[$pdf, 'ver'], [$docx, 'descargar']],
            DB::table('ofi_archivo_prestamo_accesos')->where('prestamo_id', $id)->orderBy('id')->get()
                ->map(fn ($a) => [$a->documento_id, $a->accion])->all()
        );

        // El archivo y el solicitante ven la traza; un tercero no
        $this->actingAs($this->archivista)->getJson("/api/archivo/prestamos/{$id}/accesos")
            ->assertOk()->assertJsonPath('pagination.total', 2)->assertJsonPath('data.0.accion', 'descargar');
        $this->actingAs($this->otro)->getJson("/api/archivo/prestamos/{$id}/accesos")->assertNotFound();
    }

    public function test_solo_el_solicitante_y_mientras_este_vigente(): void
    {
        [$expId, [$pdf]] = $this->expediente();
        $id = $this->prestar($expId);

        $this->actingAs($this->otro)->getJson("/api/archivo/prestamos/{$id}/consulta")->assertForbidden();
        $this->actingAs($this->archivista)->getJson("/api/archivo/prestamos/{$id}/documentos/{$pdf}")->assertForbidden();

        // Un documento de otro expediente no se abre por este préstamo
        [, [$ajeno]] = $this->expediente();
        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$id}/documentos/{$ajeno}")->assertUnprocessable();

        OfiArchivoPrestamo::whereKey($id)->update(['fecha_devolucion_esperada' => now()->subMinute()]);
        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$id}/consulta")->assertForbidden();

        // Un préstamo físico no se consulta en línea
        $fisico = OfiArchivoPrestamo::create([
            'expediente_id' => $expId, 'solicitante_id' => $this->solicitante->id, 'modalidad' => 'fisico',
            'estado' => 'prestado', 'fecha_prestamo' => now(), 'fecha_devolucion_esperada' => now()->addDay(),
            'usuario_registro_id' => $this->archivista->id,
        ]);
        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$fisico->id}/consulta")->assertForbidden();

        $this->assertSame(0, DB::table('ofi_archivo_prestamo_accesos')->count());
    }

    public function test_la_version_publica_limita_los_documentos_y_el_reservado_no_se_descarga(): void
    {
        [$expId, [$pdf, $png]] = $this->expediente('reservado');
        [, [$ajeno]] = $this->expediente();
        $fundamento = 'Requerimiento de la Fiscalía, oficio 123';

        $pendiente = $this->actingAs($this->solicitante)->postJson('/api/archivo/prestamos', [
            'expediente_id' => $expId, 'modalidad' => 'electronico', 'motivo' => 'Consulta para auditoría',
            'fecha_devolucion_esperada' => now()->addDays(3)->toDateString(),
        ])->json('data.id');
        foreach ([[], [$ajeno]] as $ids) {
            $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$pendiente}/aprobar", [
                'fundamento_autorizacion' => $fundamento, 'version_publica' => true, 'documentos_ids' => $ids,
            ])->assertUnprocessable()->assertJsonValidationErrors('documentos_ids');
        }
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$pendiente}/aprobar", [
            'fundamento_autorizacion' => $fundamento, 'version_publica' => true, 'documentos_ids' => [$png],
        ])->assertOk()->assertJsonPath('data.documentos_autorizados', [$png]);

        $consulta = $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$pendiente}/consulta")->assertOk();
        $this->assertSame([$png], array_column($consulta->json('data.documentos'), 'id'));
        $this->assertFalse($consulta->json('data.documentos.0.descargable'));

        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$pendiente}/documentos/{$pdf}")->assertUnprocessable();
        $this->actingAs($this->solicitante)->get("/api/archivo/prestamos/{$pendiente}/documentos/{$png}")->assertOk();
        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$pendiente}/documentos/{$png}?accion=descargar")->assertForbidden();

        // Sin versión pública se consulta el expediente completo
        $completo = $this->prestar($expId, ['fundamento_autorizacion' => $fundamento]);
        $this->assertCount(4, $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$completo}/consulta")->json('data.documentos'));
    }
}
