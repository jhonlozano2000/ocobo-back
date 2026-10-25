<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Services\ReportesUnificadoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 14.2: /api/reportes/unificado filtra por desde/hasta/estado con la columna de fecha y de estado de cada
 * módulo. Los registros se crean en 2001 para que los datos existentes no interfieran.
 */
class ReportesFiltrosTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permisos = [...array_values(ReportesUnificadoService::PERMISOS), VentanillaPqrs::PERMISO_VER_TODOS];
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo($permisos);
        Schema::disableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    /** IDs que devuelve el reporte del módulo con los filtros dados. */
    private function ids(string $modulo, array $filtros = []): array
    {
        $query = http_build_query(['modulo' => $modulo, 'per_page' => 100] + $filtros);

        return collect($this->actingAs($this->user)->getJson("/api/reportes/unificado?{$query}")
            ->assertOk()->json('data.datos'))->pluck('ID')->all();
    }

    private function recibido(string $fecha, ?string $estado): int
    {
        return DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'FIL-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
            'estado_trabajo' => $estado, 'created_at' => $fecha,
        ]);
    }

    private function expediente(string $apertura, string $estado = 'abierto'): int
    {
        return DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'FIL-'.uniqid(), 'nombre_expediente' => 'Filtros', 'anio' => 2001, 'dependencia_id' => 1,
            'serie_trd_id' => 1, 'fecha_apertura' => $apertura, 'usuario_apertura_id' => $this->user->id, 'estado' => $estado,
            'created_at' => now(),
        ]);
    }

    public function test_recibidas_filtran_por_fecha_de_radicacion_con_los_extremos_incluidos(): void
    {
        $antes = $this->recibido('2001-03-09 23:59:00', 'RECIBIDO');
        $inicio = $this->recibido('2001-03-10 00:00:00', 'RECIBIDO');
        $fin = $this->recibido('2001-03-20 18:30:00', 'TRAMITADO');
        $despues = $this->recibido('2001-03-21 00:00:00', 'RECIBIDO');

        $ids = $this->ids('recibidas', ['desde' => '2001-03-10', 'hasta' => '2001-03-20']);
        $this->assertEqualsCanonicalizing([$inicio, $fin], $ids);

        $this->assertContains($despues, $this->ids('recibidas', ['desde' => '2001-03-21']));
        $this->assertNotContains($despues, $this->ids('recibidas', ['hasta' => '2001-03-20']));
        $this->assertContains($antes, $this->ids('recibidas', ['hasta' => '2001-03-09']));
    }

    public function test_recibidas_filtran_por_estado_y_pendiente_incluye_los_sin_estado(): void
    {
        $rango = ['desde' => '2001-04-01', 'hasta' => '2001-04-30'];
        $sinEstado = $this->recibido('2001-04-02', null);
        $enProceso = $this->recibido('2001-04-03', 'EN_PROCESO');
        $tramitado = $this->recibido('2001-04-04', 'TRAMITADO');

        $this->assertSame([$enProceso], $this->ids('recibidas', $rango + ['estado' => 'EN_PROCESO']));
        $this->assertSame([$tramitado], $this->ids('recibidas', $rango + ['estado' => 'TRAMITADO']));

        // El reporte muestra «Pendiente» en los que no tienen estado: ese mismo valor sirve de filtro
        $datos = $this->actingAs($this->user)
            ->getJson('/api/reportes/unificado?'.http_build_query(['modulo' => 'recibidas', 'estado' => 'Pendiente'] + $rango))
            ->assertOk()->json('data.datos');
        $this->assertSame([$sinEstado], array_column($datos, 'ID'));
        $this->assertSame('Pendiente', $datos[0]['Estado']);
    }

    public function test_pqrs_filtran_por_estado_de_tramite(): void
    {
        // Cada PQRS tiene su propio radicado
        $pqrs = fn (string $estado, string $fecha) => DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $this->recibido($fecha, null), 'tipo_pqrs_id' => 1, 'fecha_vencimiento' => '2001-06-01',
            'estado_tramite' => $estado, 'created_at' => $fecha,
        ]);
        $pendiente = $pqrs('Pendiente', '2001-05-02');
        $respondida = $pqrs('Respondida', '2001-05-03');
        $fuera = $pqrs('Respondida', '2001-07-01');

        $rango = ['desde' => '2001-05-01', 'hasta' => '2001-05-31'];
        $this->assertSame([$respondida], $this->ids('pqrs', $rango + ['estado' => 'Respondida']));
        $this->assertSame([$pendiente], $this->ids('pqrs', $rango + ['estado' => 'Pendiente']));
        $this->assertNotContains($fuera, $this->ids('pqrs', $rango));
    }

    public function test_expedientes_filtran_por_fecha_de_apertura_y_estado(): void
    {
        $abierto = $this->expediente('2001-08-05');
        $cerrado = $this->expediente('2001-08-10', 'cerrado');
        $otroMes = $this->expediente('2001-09-01');

        $rango = ['desde' => '2001-08-01', 'hasta' => '2001-08-31'];
        $this->assertEqualsCanonicalizing([$abierto, $cerrado], $this->ids('expedientes', $rango));
        $this->assertSame([$cerrado], $this->ids('expedientes', $rango + ['estado' => 'cerrado']));
        $this->assertNotContains($otroMes, $this->ids('expedientes', $rango));
    }

    public function test_prestamos_solicitados_sin_fecha_de_prestamo_se_ubican_por_la_solicitud(): void
    {
        $expediente = $this->expediente('2001-01-01');
        $prestamo = fn (string $estado, ?string $prestado, string $solicitado) => DB::table('ofi_archivo_prestamos')->insertGetId([
            'expediente_id' => $expediente, 'solicitante_id' => $this->user->id, 'usuario_registro_id' => $this->user->id,
            'fecha_devolucion_esperada' => '2001-12-31', 'estado' => $estado, 'fecha_prestamo' => $prestado, 'created_at' => $solicitado,
        ]);
        $solicitado = $prestamo('solicitado', null, '2001-10-05 09:00:00');
        $prestado = $prestamo('prestado', '2001-10-20 10:00:00', '2001-09-28 10:00:00');
        $fuera = $prestamo('solicitado', null, '2001-11-02 09:00:00');

        $rango = ['desde' => '2001-10-01', 'hasta' => '2001-10-31'];
        $this->assertEqualsCanonicalizing([$solicitado, $prestado], $this->ids('prestamos', $rango));
        $this->assertSame([$solicitado], $this->ids('prestamos', $rango + ['estado' => 'solicitado']));
        $this->assertNotContains($fuera, $this->ids('prestamos', $rango));
    }

    public function test_transferencias_filtran_por_fecha_de_transferencia_y_estado(): void
    {
        $expediente = $this->expediente('2001-01-01');
        $transferencia = fn (string $estado, string $fecha) => DB::table('ofi_archivo_transferencias')->insertGetId([
            'expediente_id' => $expediente, 'tipo' => 'primaria', 'origen' => 'Gestión', 'destino' => 'Central',
            'responsable_origen_id' => $this->user->id, 'usuario_registro_id' => $this->user->id,
            'fecha_transferencia' => $fecha, 'estado' => $estado, 'created_at' => now(),
        ]);
        $pendiente = $transferencia('pendiente', '2001-02-03 08:00:00');
        $aprobada = $transferencia('aprobada', '2001-02-27 16:00:00');
        $fuera = $transferencia('aprobada', '2001-03-01 08:00:00');

        $rango = ['desde' => '2001-02-01', 'hasta' => '2001-02-28'];
        $this->assertEqualsCanonicalizing([$pendiente, $aprobada], $this->ids('transferencias', $rango));
        $this->assertSame([$aprobada], $this->ids('transferencias', $rango + ['estado' => 'aprobada']));
        $this->assertNotContains($fuera, $this->ids('transferencias', $rango));
    }

    public function test_la_exportacion_aplica_los_mismos_filtros(): void
    {
        $dentro = DB::table('ventanilla_radica_reci')->where('id', $this->recibido('2001-06-10', 'EN_PROCESO'))->value('num_radicado');
        $otroEstado = DB::table('ventanilla_radica_reci')->where('id', $this->recibido('2001-06-11', 'TRAMITADO'))->value('num_radicado');

        $csv = $this->actingAs($this->user)
            ->get('/api/reportes/export?modulo=recibidas&format=csv&desde=2001-06-01&hasta=2001-06-30&estado=EN_PROCESO')
            ->assertOk()->streamedContent();

        $this->assertStringContainsString($dentro, $csv);
        $this->assertStringNotContainsString($otroEstado, $csv);
    }

    public function test_valida_el_rango_de_fechas(): void
    {
        $this->actingAs($this->user)->getJson('/api/reportes/unificado?modulo=recibidas&desde=2001-05-10&hasta=2001-05-01')
            ->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->actingAs($this->user)->getJson('/api/reportes/unificado?modulo=recibidas&desde=no-es-fecha')
            ->assertUnprocessable()->assertJsonValidationErrors('desde');
        $this->actingAs($this->user)->getJson('/api/reportes/unificado?modulo=recibidas&estado='.str_repeat('x', 51))
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
    }
}
