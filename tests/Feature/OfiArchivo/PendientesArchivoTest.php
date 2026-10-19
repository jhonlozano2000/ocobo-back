<?php

namespace Tests\Feature\OfiArchivo;

use App\Console\Commands\AlertarExpedientesInactivosCommand;
use App\Models\Configuracion\ConfigVarias;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\OfiArchivo\ConfiguracionArchivo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Bandeja "Pendientes de archivar" (diseño de expedientes §3.4 y §6.1) y aviso de inactividad
 * (decisión D4): cada sección respeta el acceso del usuario y el aviso no se repite sin leer.
 */
class PendientesArchivoTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('workflow_instancias', 'radicado_id') || ! Schema::hasColumn('ofi_archivo_expedientes', 'nivel_acceso')) {
            $this->markTestSkipped('Requiere el origen de instancias (M19-30) y la migración 2026_10_19_100000.');
        }

        ConfigVarias::setValor(ConfiguracionArchivo::MESES_INACTIVIDAD, 6);
        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        foreach (['Ver', 'Incorporar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }
        Schema::disableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    private function recibido(User $usuario, string $estado): int
    {
        return DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'PEN-'.uniqid(), 'asunto' => 'Pendiente', 'medio_recep_id' => 1, 'clasifica_documen_id' => 1,
            'usuario_crea' => $usuario->id, 'estado_trabajo' => $estado, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function expediente(User $apertura, string $apertura_fecha, string $estado = 'abierto'): int
    {
        return DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'PEN-'.uniqid(), 'nombre_expediente' => 'Expediente pendiente', 'anio' => 2026,
            'dependencia_id' => 999999, 'serie_trd_id' => 1, 'estado' => $estado, 'nivel_acceso' => 'publico',
            'fecha_apertura' => $apertura_fecha, 'usuario_apertura_id' => $apertura->id,
        ]);
    }

    private function incorporar(int $expediente, int $radicado, string $fecha): void
    {
        DB::table('ofi_archivo_expedientes_documentos')->insert([
            'expediente_id' => $expediente, 'documentable_type' => VentanillaRadicaReci::class, 'documentable_id' => $radicado,
            'numero_folio' => 1, 'orden' => 1, 'tipo_documental' => 'Oficio', 'fecha_documento' => '2026-01-10', 'asunto' => 'Folio', 'usuario_id' => $this->user->id, 'activo' => true, 'created_at' => $fecha, 'updated_at' => $fecha,
        ]);
    }

    private function instancia(int $radicado, array $archivo): int
    {
        $workflow = DB::table('workflows')->insertGetId(['uuid' => uniqid(), 'nombre' => 'Flujo pendiente']);

        return DB::table('workflow_instancias')->insertGetId([
            'workflow_id' => $workflow, 'usuario_ejecuta_id' => $this->user->id, 'estado' => 'completada',
            'radicado_id' => $radicado, 'fecha_inicio' => now(), 'fecha_fin' => now(),
            'datos_contexto' => json_encode(['expediente' => $archivo]), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_la_bandeja_reune_lo_pendiente_dentro_del_alcance(): void
    {
        $respondido = $this->recibido($this->user, 'FINALIZADO');
        $this->recibido($this->user, 'EN_PROCESO');
        $archivado = $this->recibido($this->user, 'TRAMITADO');
        $ajeno = $this->recibido($this->otro, 'FINALIZADO');

        $reciente = $this->expediente($this->user, now()->subYear()->toDateString());
        $this->incorporar($reciente, $archivado, now()->subMonth()->toDateTimeString());
        $inactivo = $this->expediente($this->user, now()->subYear()->toDateString());
        $this->incorporar($inactivo, $this->recibido($this->user, 'RECIBIDO'), now()->subMonths(7)->toDateTimeString());
        $this->expediente($this->otro, now()->subYear()->toDateString());
        $this->expediente($this->user, now()->subYear()->toDateString(), 'cerrado');

        $sinExpediente = $this->instancia($respondido, ['estado' => 'sin_expediente', 'archivos' => 2]);
        $this->instancia($archivado, ['estado' => 'incorporado']);
        $this->instancia($ajeno, ['estado' => 'error']);

        $data = $this->actingAs($this->user)->getJson('/api/archivo/expedientes/pendientes-archivar')->assertOk()->json('data');

        $this->assertSame([$respondido], array_column($data['radicados'], 'id'));
        $this->assertSame([$sinExpediente], array_column($data['instancias'], 'id'));
        $this->assertSame('sin_expediente', $data['instancias'][0]['archivo']['estado']);
        $this->assertSame([$inactivo], array_column($data['inactivos'], 'id'));
        $this->assertSame(['radicados' => 1, 'instancias' => 1, 'inactivos' => 1], $data['totales']);
        $this->assertSame(6, $data['meses_inactividad']);
    }

    public function test_reintentar_solo_instancias_visibles_y_explica_si_sigue_sin_expediente(): void
    {
        $propia = $this->instancia($this->recibido($this->user, 'FINALIZADO'), ['estado' => 'sin_expediente']);
        $ajena = $this->instancia($this->recibido($this->otro, 'FINALIZADO'), ['estado' => 'sin_expediente']);
        $url = fn ($id) => "/api/archivo/expedientes/pendientes-archivar/instancias/{$id}/reintentar";

        $this->actingAs($this->user)->postJson($url($ajena))->assertNotFound();
        $this->postJson($url($propia))->assertUnprocessable()
            ->assertJsonPath('message', 'El radicado de origen no está en un expediente abierto; incorpore los archivos del flujo manualmente.');
    }

    public function test_el_aviso_de_inactividad_no_se_repite_sin_leer(): void
    {
        $inactivo = $this->expediente($this->user, now()->subMonths(8)->toDateString());
        $this->expediente($this->user, now()->subMonths(2)->toDateString());

        $this->artisan('archivo:alertar-expedientes-inactivos')->assertSuccessful();
        $this->artisan('archivo:alertar-expedientes-inactivos')->assertSuccessful();

        $avisos = Notificacion::where('user_id', $this->user->id)->where('type', AlertarExpedientesInactivosCommand::TYPE)->get();
        $this->assertCount(1, $avisos);
        $this->assertSame($inactivo, $avisos[0]->data['expediente_id']);

        // Con otro umbral el reciente también aplica
        $this->artisan('archivo:alertar-expedientes-inactivos --meses=1')->assertSuccessful();
        $this->assertSame(2, Notificacion::where('user_id', $this->user->id)->where('type', AlertarExpedientesInactivosCommand::TYPE)->count());
    }
}
