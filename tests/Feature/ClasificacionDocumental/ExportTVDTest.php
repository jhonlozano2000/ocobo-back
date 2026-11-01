<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * La tabla de la TVD exportaba en el navegador los registros crudos; el /tvd/export del back era un
 * volcado sin membrete, sin PDF, sin el filtro de estado y sin la selección.
 */
class ExportTVDTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $periodo;

    private int $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('TVD -> Exportar', 'web'));

        $fondo = DB::table('fondos_acumulados')->insertGetId([
            'codigo' => 'F-'.uniqid(), 'nombre' => 'Fondo XTVD', 'entidad_productora' => 'Entidad', 'created_at' => now(),
        ]);
        $this->periodo = DB::table('fondo_acumulado_periodos')->insertGetId([
            'fondo_id' => $fondo, 'nombre' => 'Período XTVD', 'fecha_inicio' => '1980-01-01', 'fecha_fin' => '1990-12-31', 'created_at' => now(),
        ]);
        $this->unidad = DB::table('fondo_acumulado_unidades')->insertGetId([
            'periodo_id' => $this->periodo, 'codigo' => 'U-'.uniqid(), 'nombre' => 'Unidad XTVD', 'created_at' => now(),
        ]);
    }

    private function elemento(string $nombre, bool $estado = true): int
    {
        return DB::table('clasificacion_documental_tvd')->insertGetId([
            'periodo_id' => $this->periodo, 'unidad_id' => $this->unidad, 'tipo' => 'SerieDocumental', 'cod' => uniqid(), 'nom' => $nombre,
            'estado' => $estado, 'created_at' => now(),
        ]);
    }

    private function filas(string $query): array
    {
        $contenido = $this->actingAs($this->user)
            ->get("/api/clasifica-documental/tvd/export?format=csv&periodo_id={$this->periodo}&".$query)
            ->assertOk()->streamedContent();

        return array_map('str_getcsv', array_values(array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF")))));
    }

    public function test_aplica_busqueda_estado_y_seleccion(): void
    {
        $activa = $this->elemento('Actas XTVD');
        $this->elemento('Actas XTVD otra');
        $this->elemento('Actas XTVD inactiva', false);
        $this->elemento('Contratos XTVD');

        $activas = $this->filas('buscar=Actas&estado=1');
        $this->assertSame(['Fondo', 'Período'], array_slice($activas[0], 0, 2));
        $this->assertEqualsCanonicalizing(['Actas XTVD', 'Actas XTVD otra'], array_column(array_slice($activas, 1), 5));
        $this->assertSame(['Fondo XTVD', 'Período XTVD'], array_slice($activas[1], 0, 2));
        $this->assertSame('Activo', $activas[1][11]);

        $inactivas = $this->filas('estado=0');
        $this->assertCount(2, $inactivas);
        $this->assertSame(['Actas XTVD inactiva', 'Inactivo'], [$inactivas[1][5], $inactivas[1][11]]);

        $seleccion = $this->filas("ids[]={$activa}");
        $this->assertCount(2, $seleccion);
        $this->assertSame('Actas XTVD', $seleccion[1][5]);
    }

    public function test_valida_formato_y_permiso(): void
    {
        $this->actingAs($this->user)->getJson('/api/clasifica-documental/tvd/export?format=doc')->assertStatus(422);

        $sinPermiso = User::factory()->create(['estado' => 1]);
        $this->actingAs($sinPermiso)->getJson('/api/clasifica-documental/tvd/export?format=csv')->assertForbidden();
    }
}
