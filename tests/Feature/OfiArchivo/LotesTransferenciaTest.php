<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use App\Services\OfiArchivo\IndiceExpedienteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B17 (diseño de expedientes §3.7): la transferencia se registraba expediente por expediente y se
 * aprobaba sin FUID, sin cotejo y sin acta de entrega, y sin validar préstamos, índice firmado ni la
 * ubicación de los físicos.
 */
class LotesTransferenciaTest extends TestCase
{
    use DatabaseTransactions;

    private User $envia;

    private User $recibe;

    private int $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envia = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Envía']);
        $this->recibe = User::factory()->create(['estado' => 1, 'nombres' => 'Luis', 'apellidos' => 'Recibe']);
        $permisos = ['Transferencias -> Ver', 'Transferencias -> Crear', 'Transferencias -> Recibir', 'Transferencias -> Aprobar', 'Expedientes -> Ver Todos'];
        foreach ($permisos as $accion) {
            $permiso = Permission::findOrCreate('Gestion de Archivo -> '.$accion, 'web');
            $this->envia->givePermissionTo($permiso);
            $this->recibe->givePermissionTo($permiso);
        }
        $this->dependencia = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Dependencia', 'nom_organico' => 'Oficina B17', 'cod_organico' => 'B17',
        ]);
    }

    /**
     * Expediente cerrado hace 3 años (2 en gestión, 3 en central, conservación total), con su
     * índice firmado salvo que se pida lo contrario.
     */
    private function expediente(array $extra = [], bool $firmado = true, float $aniosCerrado = 3): int
    {
        Schema::disableForeignKeyConstraints();
        $serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'L'.random_int(1000, 9999), 'nom' => 'Contratos', 'user_register' => $this->envia->id,
            'a_g' => '2', 'a_c' => '3', 'ct' => 1, 'e' => 0, 'm_d' => 0, 's' => 0,
        ]);
        $id = DB::table('ofi_archivo_expedientes')->insertGetId($extra + [
            'numero_expediente' => 'B17-'.uniqid(), 'nombre_expediente' => 'Contrato de prueba', 'anio' => 2020,
            'dependencia_id' => $this->dependencia, 'dependencia_cod' => 'B17', 'dependencia_nom' => 'Oficina B17',
            'serie_trd_id' => $serie, 'estado' => 'cerrado', 'fecha_apertura' => now()->subYears(5),
            'usuario_apertura_id' => $this->envia->id, 'fecha_cierre' => now()->subDays((int) round($aniosCerrado * 365)),
        ]);
        Schema::enableForeignKeyConstraints();

        if ($firmado) {
            app(IndiceExpedienteService::class)->versionar(OfiArchivoExpediente::findOrFail($id), $this->envia);
        }

        return $id;
    }

    private function enviar(array $expedientes, string $tipo = 'primaria')
    {
        return $this->actingAs($this->envia)->postJson('/api/archivo/transferencia-lotes', [
            'tipo' => $tipo, 'dependencia_id' => $this->dependencia, 'origen' => 'Archivo de gestión B17',
            'destino' => 'Archivo central', 'expediente_ids' => $expedientes,
        ]);
    }

    /** @return array<int, int> id del detalle por id del expediente */
    private function items(int $lote): array
    {
        return DB::table('ofi_archivo_transferencias')->where('lote_id', $lote)->pluck('id', 'expediente_id')->all();
    }

    private function estado(int $expediente): array
    {
        return (array) DB::table('ofi_archivo_expedientes')->where('id', $expediente)->first(['estado', 'fase']);
    }

    public function test_envia_el_lote_con_fuid_y_lo_recibe_con_acta_firmada(): void
    {
        $a = $this->expediente();
        $b = $this->expediente(['soporte' => 'fisico', 'ubicacion_fisica' => 'Depósito 1', 'caja' => '12', 'carpeta' => '3', 'folios_fisicos' => 87]);

        $lote = $this->enviar([$a, $b])->assertCreated()->assertJsonPath('data.estado', 'enviado')->json('data');
        $this->assertMatchesRegularExpression('/^TP-\d{4}-\d{4}$/', $lote['codigo']);
        $this->assertSame(['estado' => 'en_transferencia', 'fase' => 'gestion'], $this->estado($a));
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $a, 'accion' => 'envio_transferencia']);

        // El FUID trae cada expediente y su huella es la firmada
        $fuid = $this->actingAs($this->envia)->get("/api/archivo/transferencia-lotes/{$lote['id']}/fuid")->assertOk();
        $this->assertSame(hash('sha256', $fuid->getContent()), $fuid->headers->get('X-Hash-Sha256'));
        $ruta = tempnam(sys_get_temp_dir(), 'fuid');
        file_put_contents($ruta, $fuid->getContent());
        $hoja = IOFactory::load($ruta)->getActiveSheet()->toArray();
        unlink($ruta);
        $celdas = collect($hoja)->flatten()->filter()->map(fn ($v) => (string) $v);
        $this->assertTrue($celdas->contains('FORMATO ÚNICO DE INVENTARIO DOCUMENTAL (FUID)'));
        $this->assertTrue($celdas->contains('Físico') && $celdas->contains('87') && $celdas->contains('12'));
        $numeros = DB::table('ofi_archivo_expedientes')->whereIn('id', [$a, $b])->pluck('numero_expediente');
        foreach ($numeros as $numero) {
            $this->assertTrue($celdas->contains(fn ($v) => str_contains($v, $numero)), "El FUID no lista {$numero}");
        }

        $items = $this->items($lote['id']);
        $cotejo = fn (array $ids) => array_map(fn ($id) => ['id' => $id, 'conforme' => true], array_values($ids));

        // Quien envía no recibe, y el cotejo debe cubrir todo el lote
        $this->actingAs($this->envia)->postJson("/api/archivo/transferencia-lotes/{$lote['id']}/recibir", ['items' => $cotejo($items)])->assertStatus(422);
        $this->actingAs($this->recibe)->postJson("/api/archivo/transferencia-lotes/{$lote['id']}/recibir", ['items' => $cotejo([$items[$a]])])->assertStatus(422);
        $this->assertSame(['estado' => 'en_transferencia', 'fase' => 'gestion'], $this->estado($b));

        $this->actingAs($this->recibe)->postJson("/api/archivo/transferencia-lotes/{$lote['id']}/recibir", ['items' => $cotejo($items)])
            ->assertOk()->assertJsonPath('data.estado', 'recibido');

        foreach ([$a, $b] as $exp) {
            $this->assertSame(['estado' => 'cerrado', 'fase' => 'central'], $this->estado($exp));
            $this->assertDatabaseHas('ofi_archivo_transferencias', ['expediente_id' => $exp, 'estado' => 'aprobada', 'cotejo' => 'conforme', 'responsable_destino_id' => $this->recibe->id]);
        }
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $a, 'accion' => 'transferencia']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->envia->id, 'type' => 'transferencia_recibida']);

        $acta = $this->actingAs($this->envia)->get("/api/archivo/transferencia-lotes/{$lote['id']}/acta")->assertOk();
        $this->assertStringStartsWith('%PDF', $acta->getContent());
        $this->actingAs($this->envia)->getJson("/api/archivo/transferencia-lotes/{$lote['id']}")->assertOk()
            ->assertJsonPath('data.integridad.integro', true)
            ->assertJsonPath('data.firmante_envio', 'Ana Envía')
            ->assertJsonPath('data.firmante_recepcion', 'Luis Recibe')
            ->assertJsonMissingPath('data.acta');

        // Recibido es definitivo
        $this->actingAs($this->recibe)->postJson("/api/archivo/transferencia-lotes/{$lote['id']}/recibir", ['items' => $cotejo($items)])->assertStatus(422);

        // Un acta alterada ya no supera la verificación
        DB::table('ofi_archivo_transferencia_lotes')->where('id', $lote['id'])->update(['acta' => $acta->getContent().' ']);
        $this->actingAs($this->envia)->getJson("/api/archivo/transferencia-lotes/{$lote['id']}")
            ->assertJsonPath('data.integridad.integro', false);
    }

    public function test_el_lote_rechaza_los_expedientes_que_no_pueden_transferirse(): void
    {
        $apto = $this->expediente();
        $sinFirma = $this->expediente([], false);
        $prestado = $this->expediente();
        DB::table('ofi_archivo_prestamos')->insert([
            'expediente_id' => $prestado, 'solicitante_id' => $this->envia->id, 'fecha_prestamo' => now()->subDays(3),
            'fecha_devolucion_esperada' => now()->addDays(3), 'estado' => 'prestado', 'usuario_registro_id' => $this->envia->id,
        ]);
        $fisicoSinCaja = $this->expediente(['soporte' => 'fisico', 'ubicacion_fisica' => 'Depósito 1']);
        $sinRetencion = $this->expediente([], true, 1);
        $otraDependencia = $this->expediente(['dependencia_id' => 999999]);

        $casos = [
            $sinFirma => 'índice electrónico firmado',
            $prestado => 'préstamos sin devolver',
            $fisicoSinCaja => 'caja, carpeta',
            $sinRetencion => 'tiempo de retención',
            $otraDependencia => 'dependencia del lote',
        ];
        foreach ($casos as $exp => $motivo) {
            $errores = $this->enviar([$apto, $exp])->assertStatus(422)->json('errors.expediente_ids');
            $this->assertCount(1, $errores);
            $this->assertStringContainsString($motivo, $errores[0]);
        }
        $this->assertDatabaseCount('ofi_archivo_transferencia_lotes', 0);
        $this->assertSame(['estado' => 'cerrado', 'fase' => 'gestion'], $this->estado($apto));

        // Los candidatos muestran el motivo de cada uno
        $candidatos = collect($this->actingAs($this->envia)->getJson("/api/archivo/transferencia-lotes/candidatos?tipo=primaria&dependencia_id={$this->dependencia}")
            ->assertOk()->json('data'))->keyBy('id');
        $this->assertNull($candidatos[$apto]['motivo']);
        $this->assertStringContainsString('préstamos', $candidatos[$prestado]['motivo']);

        // Un expediente en un lote enviado no entra en otro, ni en una secundaria si sigue en gestión
        $this->enviar([$apto])->assertCreated();
        $this->assertStringContainsString('transferencia pendiente', $this->enviar([$apto])->assertStatus(422)->json('errors.expediente_ids.0'));
        $this->assertStringContainsString('archivo central', $this->enviar([$this->expediente([], true, 6)], 'secundaria')->assertStatus(422)->json('errors.expediente_ids.0'));
    }

    public function test_devolver_exige_observaciones_y_regresa_los_expedientes_a_gestion(): void
    {
        $a = $this->expediente();
        $b = $this->expediente();
        $lote = $this->enviar([$a, $b])->assertCreated()->json('data.id');
        $items = $this->items($lote);
        $recibir = fn (array $datos) => $this->actingAs($this->recibe)->postJson("/api/archivo/transferencia-lotes/{$lote}/recibir", $datos);

        // El no conforme exige su motivo y la devolución, sus observaciones
        $recibir(['items' => [['id' => $items[$a], 'conforme' => true], ['id' => $items[$b], 'conforme' => false]], 'observaciones' => 'Falta un folio'])->assertStatus(422);
        $recibir(['items' => [['id' => $items[$a], 'conforme' => true], ['id' => $items[$b], 'conforme' => false, 'observacion' => 'Folio 3 ilegible']]])->assertStatus(422);
        $this->assertSame(['estado' => 'en_transferencia', 'fase' => 'gestion'], $this->estado($b));

        $recibir([
            'items' => [['id' => $items[$a], 'conforme' => true], ['id' => $items[$b], 'conforme' => false, 'observacion' => 'Folio 3 ilegible']],
            'observaciones' => 'Corregir el expediente B y reenviar',
        ])->assertOk()->assertJsonPath('data.estado', 'devuelto');

        foreach ([$a, $b] as $exp) {
            $this->assertSame(['estado' => 'cerrado', 'fase' => 'gestion'], $this->estado($exp));
            $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $exp, 'accion' => 'transferencia_devuelta']);
        }
        $this->assertDatabaseHas('ofi_archivo_transferencias', ['id' => $items[$b], 'estado' => 'rechazada', 'cotejo' => 'no_conforme', 'observacion_cotejo' => 'Folio 3 ilegible']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->envia->id, 'type' => 'transferencia_devuelta']);
        $this->actingAs($this->envia)->get("/api/archivo/transferencia-lotes/{$lote}/acta")->assertNotFound();
        $this->actingAs($this->envia)->getJson("/api/archivo/transferencia-lotes/{$lote}")->assertJsonPath('data.integridad.integro', true);

        // Corregido, se envía en un lote nuevo
        $this->assertStringEndsWith('-0002', $this->enviar([$a, $b])->assertCreated()->json('data.codigo'));
    }

    public function test_la_transferencia_suelta_ya_no_se_registra_y_la_del_lote_no_se_aprueba_suelta(): void
    {
        $exp = $this->expediente();
        $this->actingAs($this->envia)->postJson('/api/archivo/transferencias', [
            'expediente_id' => $exp, 'tipo' => 'primaria', 'origen' => 'G', 'destino' => 'C',
            'responsable_origen_id' => $this->envia->id, 'fecha_transferencia' => now()->toDateString(),
        ])->assertStatus(422);
        $this->assertDatabaseMissing('ofi_archivo_transferencias', ['expediente_id' => $exp]);

        $lote = $this->enviar([$exp])->assertCreated()->json('data.id');
        $item = $this->items($lote)[$exp];
        $this->actingAs($this->recibe)->postJson("/api/archivo/transferencias/{$item}/aprobar", ['estado' => 'aprobada'])->assertStatus(422);
        $this->assertSame(['estado' => 'en_transferencia', 'fase' => 'gestion'], $this->estado($exp));
    }

    public function test_la_secundaria_por_lote_lo_deja_transferido_en_el_historico(): void
    {
        $exp = $this->expediente(['fase' => 'central'], false, 6);
        $lote = $this->enviar([$exp], 'secundaria')->assertCreated()->json('data');
        $this->assertStringStartsWith('TS-', $lote['codigo']);

        $this->actingAs($this->recibe)->postJson("/api/archivo/transferencia-lotes/{$lote['id']}/recibir", [
            'items' => [['id' => $this->items($lote['id'])[$exp], 'conforme' => true]],
        ])->assertOk();
        $this->assertSame(['estado' => 'transferido', 'fase' => 'historico'], $this->estado($exp));
    }
}
