<?php

namespace Tests\Feature\Dashboard;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Dashboard eCommerce de la plantilla alimentado con la gestión de archivo: estructura de la
 * respuesta y alcance (expedientes visibles para el usuario y préstamos propios).
 */
class EcommerceDashboardTest extends TestCase
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

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);

        Schema::disableForeignKeyConstraints();
        $this->depA = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep A', 'cod_organico' => 'DSHA']);
        $cargo = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Cargo', 'nom_organico' => 'Cargo A', 'cod_organico' => 'DSHAC', 'parent' => $this->depA]);
        $this->depB = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B', 'cod_organico' => 'DSHB']);
        DB::table('users_cargos')->insert(['user_id' => $this->user->id, 'cargo_id' => $cargo, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'SDSH', 'nom' => 'Serie dashboard']);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(int $dependenciaId, string $numero): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => $numero,
            'nombre_expediente' => 'Expediente '.$numero,
            'anio' => now()->year,
            'dependencia_id' => $dependenciaId,
            'serie_trd_id' => $this->serieId,
            'estado' => 'abierto',
            'fecha_apertura' => now(),
            'usuario_apertura_id' => $this->otro->id,
        ] + (Schema::hasColumn('ofi_archivo_expedientes', 'nivel_acceso') ? ['nivel_acceso' => 'publico'] : []));
    }

    private function solicitud(OfiArchivoExpediente $expediente, User $solicitante): void
    {
        OfiArchivoPrestamo::create([
            'expediente_id' => $expediente->id, 'solicitante_id' => $solicitante->id, 'usuario_registro_id' => $solicitante->id,
            'fecha_prestamo' => now(), 'fecha_devolucion_esperada' => now()->addDays(5), 'estado' => 'solicitado',
        ]);
    }

    public function test_entrega_la_estructura_del_dashboard(): void
    {
        $this->actingAs($this->user)->getJson('/api/dashboard/ecommerce')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'bienvenida' => ['nombre', 'documentos_mes', 'expedientes_mes'],
                'estadisticas' => ['expedientes', 'prestamos_activos', 'transferencias_pendientes', 'documentos', 'actualizado'],
                'documentos_mes' => ['total', 'serie', 'variacion', 'tendencia'],
                'expedientes_cerrados' => ['cerrados', 'total', 'porcentaje', 'cierres_mes', 'texto'],
                'fases' => ['labels', 'series', 'total', 'variacion', 'tendencia'],
                'reporte_anual' => ['categorias', 'anios' => [['anio', 'abiertos', 'cerrados', 'abiertos_anterior', 'total_abiertos', 'total_cerrados']]],
                'prestamos_semana' => ['items' => [['titulo', 'subtitulo', 'valor', 'variacion', 'tendencia', 'color', 'icono']], 'serie', 'categorias'],
                'series_trd' => ['total', 'items'],
                'prestamos_por_estado' => ['en_curso', 'pendientes' => ['total', 'items'], 'prestados' => ['total', 'items'], 'vencidos' => ['total', 'items']],
                'movimientos' => ['total_mes', 'items'],
                'expedientes',
            ]]);
    }

    public function test_solo_muestra_expedientes_visibles_y_prestamos_propios(): void
    {
        $propio = $this->expediente($this->depA, 'DSH-A-'.uniqid());
        $ajeno = $this->expediente($this->depB, 'DSH-B-'.uniqid());
        $this->solicitud($propio, $this->user);
        $this->solicitud($ajeno, $this->otro);

        $datos = $this->actingAs($this->user)->getJson('/api/dashboard/ecommerce')->assertOk()->json('data');

        $expedientes = collect($datos['expedientes'])->pluck('numero');
        $this->assertContains($propio->numero_expediente, $expedientes);
        $this->assertNotContains($ajeno->numero_expediente, $expedientes);

        $movimientos = collect($datos['movimientos']['items'])->pluck('subtitulo')->implode('|');
        $this->assertStringContainsString($propio->numero_expediente, $movimientos);
        $this->assertStringNotContainsString($ajeno->numero_expediente, $movimientos);

        $prestamos = collect($datos['prestamos_por_estado']['pendientes']['items'])->pluck('expediente');
        $this->assertContains($propio->numero_expediente, $prestamos);
        $this->assertNotContains($ajeno->numero_expediente, $prestamos);

        $serie = collect($datos['series_trd']['items'])->firstWhere('id', $this->serieId);
        $this->assertSame(1, $serie['total']);
    }
}
