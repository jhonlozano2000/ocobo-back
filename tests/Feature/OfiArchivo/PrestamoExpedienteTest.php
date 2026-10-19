<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use App\Services\OfiArchivo\ConfiguracionArchivo;
use App\Services\OfiArchivo\PrestamosService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Préstamo y consulta (diseño de expedientes §3.10): solicitud del expediente o de un tomo, en sala,
 * físico o electrónico; aprobación del responsable del archivo de la fase; entrega y devolución
 * con folios; reserva. M08-12: nunca se presta un eliminado ni dos veces a la vez.
 */
class PrestamoExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Gestion de Archivo -> Prestamos -> ';

    /** Responsable del archivo de gestión de la dependencia A. */
    private User $archivista;

    /** Archivo central: ve todos los expedientes. */
    private User $central;

    private User $solicitante;

    private int $depA;

    private int $depB;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->archivista, $this->central, $this->solicitante] = User::factory()->count(3)->create(['estado' => 1])->all();
        foreach ([$this->archivista, $this->central] as $user) {
            foreach (['Ver', 'Aprobar', 'Devolver', 'Solicitar'] as $accion) {
                $user->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
            }
        }
        $this->central->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver Todos', 'web'));
        $this->solicitante->givePermissionTo(Permission::findOrCreate(self::PERM.'Solicitar', 'web'));
        Permission::findOrCreate(PrestamosService::PERMISO_VER_RESERVADOS, 'web');

        Schema::disableForeignKeyConstraints();
        $this->depA = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep A', 'cod_organico' => 'P10A']);
        $cargo = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Cargo', 'nom_organico' => 'Archivista A', 'cod_organico' => 'P10AC', 'parent' => $this->depA]);
        $this->depB = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B', 'cod_organico' => 'P10B']);
        DB::table('users_cargos')->insert(['user_id' => $this->archivista->id, 'cargo_id' => $cargo, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(array $datos = []): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ofi_archivo_expedientes')->insertGetId($datos + [
            'numero_expediente' => 'PR-'.uniqid(),
            'nombre_expediente' => 'Expediente Prueba Prestamo',
            'anio' => 2024,
            'dependencia_id' => $this->depA,
            'serie_trd_id' => 1,
            'estado' => 'cerrado',
            'fase' => 'gestion',
            'soporte' => 'fisico',
            'nivel_acceso' => 'publico',
            'fecha_apertura' => now()->subYears(2),
            'usuario_apertura_id' => $this->archivista->id,
            'fecha_cierre' => now()->subYear(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    /** B19: los tomos solo se abren con el expediente abierto. */
    private function tomo(int $expedienteId, int $numero): int
    {
        $estado = DB::table('ofi_archivo_expedientes')->where('id', $expedienteId)->value('estado');
        DB::table('ofi_archivo_expedientes')->where('id', $expedienteId)->update(['estado' => 'abierto']);
        $id = DB::table('ofi_archivo_expediente_tomos')->insertGetId([
            'expediente_id' => $expedienteId, 'numero' => $numero, 'folio_inicial' => ($numero - 1) * 200 + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ofi_archivo_expedientes')->where('id', $expedienteId)->update(['estado' => $estado]);

        return $id;
    }

    private function solicitar(int $expedienteId, array $datos = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->solicitante)->postJson('/api/archivo/prestamos', $datos + [
            'expediente_id' => $expedienteId,
            'modalidad' => 'fisico',
            'motivo' => 'Revisión del contrato',
            'fecha_devolucion_esperada' => now()->addDays(5)->toDateString(),
        ]);
    }

    /** Solicita, aprueba y entrega con 30 folios. */
    private function prestar(int $expedienteId, array $datos = []): int
    {
        $id = $this->solicitar($expedienteId, $datos)->assertCreated()->json('data.id');
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar")->assertOk();
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/entregar", ['folios' => 30])->assertOk();

        return $id;
    }

    public function test_la_solicitud_espera_aprobacion_y_el_responsable_entrega_con_folios(): void
    {
        $expId = $this->expediente();

        $res = $this->solicitar($expId)->assertCreated();
        $id = $res->json('data.id');
        $this->assertSame('solicitado', $res->json('data.estado'));
        $this->assertSame(['aprobar' => false, 'rechazar' => false, 'cancelar' => true, 'entregar' => false, 'devolver' => false], $res->json('data.acciones'));
        // Solicitar no saca nada del archivo: el historial no registra el préstamo todavía
        $this->assertDatabaseMissing('ofi_archivo_expediente_eventos', ['expediente_id' => $expId, 'accion' => 'prestamo']);

        // Nadie aprueba su propia solicitud; el responsable de otra dependencia tampoco
        $propia = $this->solicitar($this->expediente(), [], $this->archivista)->json('data.id');
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$propia}/aprobar")->assertForbidden();
        $ajeno = $this->solicitar($this->expediente(['dependencia_id' => $this->depB]))->json('data.id');
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$ajeno}/aprobar")->assertForbidden();
        $this->actingAs($this->solicitante)->postJson("/api/archivo/prestamos/{$id}/aprobar")->assertForbidden();

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar")
            ->assertOk()->assertJsonPath('data.estado', 'aprobado')->assertJsonPath('data.acciones.entregar', true);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->solicitante->id, 'type' => PrestamosService::TYPE_RESPUESTA]);

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/entregar", ['folios' => 0])->assertUnprocessable();
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/entregar", ['folios' => 42])
            ->assertOk()->assertJsonPath('data.estado', 'prestado')->assertJsonPath('data.folios_entrega', 42);
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $expId, 'accion' => 'prestamo']);
        $this->assertDatabaseHas('ofi_archivo_prestamos', ['id' => $id, 'entregado_por_id' => $this->archivista->id, 'aprobador_id' => $this->archivista->id]);
    }

    public function test_en_central_aprueba_el_archivo_central_y_no_la_dependencia(): void
    {
        $id = $this->solicitar($this->expediente(['fase' => 'central']))->json('data.id');

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar")->assertForbidden();
        $this->actingAs($this->central)->postJson("/api/archivo/prestamos/{$id}/aprobar")->assertOk();
    }

    public function test_valida_el_expediente_la_modalidad_el_tomo_y_el_plazo(): void
    {
        DB::table('config_varias')->updateOrInsert(['clave' => ConfiguracionArchivo::PRESTAMO_PLAZO_MAXIMO], ['valor' => '10', 'estado' => true]);
        $expId = $this->expediente();
        $otro = $this->expediente();
        $tomoAjeno = $this->tomo($otro, 1);

        $this->solicitar($this->expediente(['estado' => 'eliminado']))->assertUnprocessable();
        $this->solicitar($expId, ['fecha_devolucion_esperada' => now()->addDays(11)->toDateString()])
            ->assertUnprocessable()->assertJsonPath('errors.fecha_devolucion_esperada.0', 'El plazo máximo de un préstamo es de 10 días (hasta el '.now()->addDays(10)->toDateString().')');
        $this->solicitar($expId, ['fecha_devolucion_esperada' => now()->addDays(10)->toDateString()])->assertCreated();
        $this->solicitar($expId, ['tomo_id' => $tomoAjeno])->assertUnprocessable()->assertJsonValidationErrors('tomo_id');
        $this->solicitar($expId, ['modalidad' => 'electronico'])->assertUnprocessable()->assertJsonValidationErrors('modalidad');
        $this->solicitar($this->expediente(['soporte' => 'electronico']))->assertUnprocessable()->assertJsonValidationErrors('modalidad');
        $this->solicitar($this->expediente(['soporte' => 'hibrido']), ['modalidad' => 'electronico', 'tomo_id' => $tomoAjeno])
            ->assertUnprocessable()->assertJsonValidationErrors('tomo_id');
        $this->solicitar($expId, ['motivo' => ''])->assertUnprocessable()->assertJsonValidationErrors('motivo');

        $sinPermiso = User::factory()->create(['estado' => 1]);
        $this->solicitar($expId, [], $sinPermiso)->assertForbidden();
    }

    public function test_no_presta_dos_veces_el_expediente_ni_un_tomo_que_esta_fuera(): void
    {
        $expId = $this->expediente();
        $tomo1 = $this->tomo($expId, 1);
        $tomo2 = $this->tomo($expId, 2);

        $this->prestar($expId, ['tomo_id' => $tomo1]);

        // Otro tomo sí; el mismo tomo o el expediente completo no
        $this->solicitar($expId, ['tomo_id' => $tomo2])->assertCreated();
        $this->solicitar($expId, ['tomo_id' => $tomo1])->assertUnprocessable()
            ->assertJsonPath('message', 'El tomo 1 del expediente ya se encuentra en préstamo activo.');
        $this->solicitar($expId)->assertUnprocessable();

        // Un aprobado por entregar también aparta el expediente
        $otro = $this->expediente();
        $id = $this->solicitar($otro)->json('data.id');
        $this->solicitar($otro)->assertCreated();
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar")->assertOk();
        $this->solicitar($otro)->assertUnprocessable()->assertJsonPath('message', 'El expediente tiene un préstamo aprobado por entregar.');
    }

    public function test_si_los_folios_no_coinciden_al_devolver_se_marca_y_se_avisa(): void
    {
        $expId = $this->expediente();
        $id = $this->prestar($expId);

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/devolver")->assertUnprocessable()->assertJsonValidationErrors('folios');
        $this->actingAs($this->central)->postJson("/api/archivo/prestamos/{$id}/devolver", ['folios' => 28])
            ->assertOk()->assertJsonPath('data.folios_no_coinciden', true)
            ->assertJsonPath('message', 'Devolución registrada. Los folios no coinciden: se entregaron 30 y se devolvieron 28');

        foreach ([$this->archivista, $this->central] as $user) {
            $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => PrestamosService::TYPE_FOLIOS]);
        }
        $evento = DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $expId)->where('accion', 'devolucion')->first();
        $this->assertSame(['prestamo_id' => $id, 'folios' => 28, 'folios_entregados' => 30, 'folios_no_coinciden' => true], json_decode($evento->detalle, true));

        $bien = $this->prestar($this->expediente());
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$bien}/devolver", ['folios' => 30])
            ->assertOk()->assertJsonPath('data.folios_no_coinciden', false);
    }

    public function test_el_reservado_exige_ver_reservados_y_fundamento(): void
    {
        $id = $this->solicitar($this->expediente(['nivel_acceso' => 'reservado', 'fundamento_acceso' => 'Ley 1712 art. 19']))->json('data.id');

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar")->assertForbidden();

        $this->archivista->givePermissionTo(PrestamosService::PERMISO_VER_RESERVADOS);
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar", ['fundamento_autorizacion' => 'corto'])
            ->assertUnprocessable()->assertJsonValidationErrors('fundamento_autorizacion');
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar", [
            'fundamento_autorizacion' => 'Requerimiento de la Fiscalía, oficio 123',
            'version_publica' => true,
        ])->assertOk()->assertJsonPath('data.version_publica', true);

        // En un público el fundamento y la versión pública no aplican
        $publico = $this->solicitar($this->expediente())->json('data.id');
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$publico}/aprobar", ['fundamento_autorizacion' => 'No aplica aquí', 'version_publica' => true])
            ->assertOk()->assertJsonPath('data.version_publica', false)->assertJsonPath('data.fundamento_autorizacion', null);
    }

    public function test_el_electronico_empieza_al_aprobarse_no_aparta_el_expediente_y_expira(): void
    {
        $expId = $this->expediente(['soporte' => 'hibrido']);
        $id = $this->solicitar($expId, ['modalidad' => 'electronico'])->json('data.id');

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/aprobar")
            ->assertOk()->assertJsonPath('data.estado', 'prestado');
        // El físico del mismo expediente sigue disponible y el electrónico no cuenta como fuera del archivo
        $this->solicitar($expId)->assertCreated();
        $this->assertSame(0, OfiArchivoPrestamo::where('expediente_id', $expId)->fueraDelArchivo()->count());

        OfiArchivoPrestamo::whereKey($id)->update(['fecha_devolucion_esperada' => now()->subMinute()]);
        $this->assertSame(0, OfiArchivoPrestamo::vencidos()->whereKey($id)->count());
        $this->artisan('archivo:marcar-prestamos-vencidos')->assertSuccessful();

        $this->assertSame('devuelto', OfiArchivoPrestamo::find($id)->estado);
        $evento = DB::table('ofi_archivo_expediente_eventos')->where('expediente_id', $expId)->where('accion', 'devolucion')->first();
        $this->assertTrue(json_decode($evento->detalle, true)['acceso_expirado']);
    }

    public function test_rechazar_y_cancelar(): void
    {
        $expId = $this->expediente();
        $id = $this->solicitar($expId)->json('data.id');

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/rechazar", ['motivo' => ''])->assertUnprocessable();
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$id}/rechazar", ['motivo' => 'No hay justificación'])
            ->assertOk()->assertJsonPath('data.estado', 'rechazado')->assertJsonPath('data.motivo_rechazo', 'No hay justificación');
        $this->actingAs($this->solicitante)->postJson("/api/archivo/prestamos/{$id}/cancelar")->assertUnprocessable();

        $otra = $this->solicitar($expId)->json('data.id');
        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$otra}/cancelar")->assertForbidden();
        $this->actingAs($this->solicitante)->postJson("/api/archivo/prestamos/{$otra}/cancelar")
            ->assertOk()->assertJsonPath('data.estado', 'cancelado');
    }

    public function test_cada_quien_ve_lo_suyo_y_el_responsable_lo_que_atiende(): void
    {
        $mio = $this->solicitar($this->expediente())->json('data.id');
        $ajeno = $this->solicitar($this->expediente(['dependencia_id' => $this->depB]))->json('data.id');
        $central = $this->solicitar($this->expediente(['fase' => 'central']))->json('data.id');

        $ids = fn ($res) => collect($res->assertOk()->json('data'))->pluck('id')->intersect([$mio, $ajeno, $central])->values()->all();

        $this->assertSame([$central, $ajeno, $mio], $ids($this->actingAs($this->solicitante)->getJson('/api/archivo/prestamos?per_page=100')));
        $this->actingAs($this->solicitante)->getJson('/api/archivo/prestamos?bandeja=todos')->assertForbidden();
        $this->assertSame([$mio], $ids($this->actingAs($this->archivista)->getJson('/api/archivo/prestamos?bandeja=por_atender&per_page=100')));
        $this->assertSame([$central, $ajeno, $mio], $ids($this->actingAs($this->central)->getJson('/api/archivo/prestamos?bandeja=por_atender&per_page=100')));

        $otro = User::factory()->create(['estado' => 1]);
        $this->actingAs($otro)->getJson("/api/archivo/prestamos/{$mio}")->assertNotFound();
        $this->actingAs($this->solicitante)->getJson("/api/archivo/prestamos/{$mio}")->assertOk();
    }

    public function test_busca_expedientes_prestables_con_sus_tomos(): void
    {
        $expId = $this->expediente(['numero_expediente' => 'PRB-001']);
        $this->tomo($expId, 1);
        $this->expediente(['numero_expediente' => 'PRB-002', 'estado' => 'eliminado']);
        $this->prestar($expId);

        $res = $this->actingAs($this->solicitante)->getJson('/api/archivo/prestamos/expedientes?search=PRB-00')->assertOk();

        $this->assertSame(['PRB-001'], collect($res->json('data.expedientes'))->pluck('numero_expediente')->all());
        $this->assertTrue($res->json('data.expedientes.0.prestado'));
        $this->assertSame([1], collect($res->json('data.expedientes.0.tomos'))->pluck('numero')->all());
        $this->assertSame(ConfiguracionArchivo::prestamoPlazoMaximo(), $res->json('data.plazo_maximo_dias'));
    }

    public function test_un_prestamo_anterior_vencido_se_devuelve_sin_folios(): void
    {
        $prestamo = OfiArchivoPrestamo::create([
            'expediente_id' => $this->expediente(),
            'solicitante_id' => $this->solicitante->id,
            'fecha_prestamo' => now()->subDays(10),
            'fecha_devolucion_esperada' => now()->subDays(2),
            'estado' => 'vencido',
            'usuario_registro_id' => $this->archivista->id,
        ]);

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$prestamo->id}/devolver")
            ->assertOk()->assertJsonPath('data.estado', 'devuelto');
    }
}
