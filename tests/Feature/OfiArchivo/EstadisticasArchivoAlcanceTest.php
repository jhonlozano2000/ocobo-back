<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Dashboard de archivo: los totales se comparten entre usuarios, pero los últimos expedientes
 * y la actividad reciente (números, nombres, solicitantes) solo muestran expedientes visibles.
 */
class EstadisticasArchivoAlcanceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $otro;

    private int $depA;

    private int $depB;

    private int $serieId;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('archivo_estadisticas_agregadas');

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Dashboard -> Ver', 'web'));

        Schema::disableForeignKeyConstraints();
        $this->depA = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep A', 'cod_organico' => 'E91A']);
        $oficina = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Oficina', 'nom_organico' => 'Of A1', 'cod_organico' => 'E91A1', 'parent' => $this->depA]);
        $cargo = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Cargo', 'nom_organico' => 'Cargo A1', 'cod_organico' => 'E91A1C', 'parent' => $oficina]);
        $this->depB = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B', 'cod_organico' => 'E91B']);
        DB::table('users_cargos')->insert(['user_id' => $this->user->id, 'cargo_id' => $cargo, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'S91']);
        Schema::enableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Cache::forget('archivo_estadisticas_agregadas');

        parent::tearDown();
    }

    private function expedienteConPrestamo(int $dependenciaId, string $numero): OfiArchivoExpediente
    {
        $expediente = OfiArchivoExpediente::create([
            'numero_expediente' => $numero,
            'nombre_expediente' => 'Expediente '.$numero,
            'anio' => 2026,
            'dependencia_id' => $dependenciaId,
            'serie_trd_id' => $this->serieId,
            'estado' => 'abierto',
            'fecha_apertura' => now()->toDateString(),
            'usuario_apertura_id' => $this->otro->id,
        ] + (Schema::hasColumn('ofi_archivo_expedientes', 'nivel_acceso') ? ['nivel_acceso' => 'publico'] : []));

        OfiArchivoPrestamo::create([
            'expediente_id' => $expediente->id, 'solicitante_id' => $this->otro->id, 'usuario_registro_id' => $this->otro->id,
            'fecha_prestamo' => now(), 'fecha_devolucion_esperada' => now()->addDays(5), 'estado' => 'prestado',
        ]);

        return $expediente;
    }

    public function test_los_listados_solo_muestran_expedientes_visibles(): void
    {
        $propio = $this->expedienteConPrestamo($this->depA, '2026-E91A-S91-0001');
        $ajeno = $this->expedienteConPrestamo($this->depB, '2026-E91B-S91-0001');

        $data = $this->actingAs($this->user)->getJson('/api/archivo/dashboard/stats')->assertOk()->json('data');

        $this->assertArrayHasKey('expedientes', $data);
        $this->assertArrayHasKey('sparklines', $data);

        $ids = collect($data['ultimos_expedientes'])->pluck('id');
        $this->assertTrue($ids->contains($propio->id));
        $this->assertFalse($ids->contains($ajeno->id));

        $actividad = collect($data['actividad_reciente'])->pluck('descripcion')->implode(' | ');
        $this->assertStringContainsString($propio->numero_expediente, $actividad);
        $this->assertStringNotContainsString($ajeno->numero_expediente, $actividad);
        $this->assertArrayNotHasKey('creado', $data['actividad_reciente'][0]);
    }

    public function test_quien_ve_todos_recibe_tambien_los_ajenos(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate(OfiArchivoExpediente::PERMISO_VER_TODOS, 'web'));
        $ajeno = $this->expedienteConPrestamo($this->depB, '2026-E91B-S91-0002');

        $data = $this->actingAs($this->user)->getJson('/api/archivo/dashboard/stats')->assertOk()->json('data');

        $this->assertTrue(collect($data['ultimos_expedientes'])->pluck('id')->contains($ajeno->id));
    }
}
