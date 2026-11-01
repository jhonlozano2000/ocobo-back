<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Expedientes se exportaba en el navegador con lo cargado en la tabla; ahora el back arma el archivo con
 * los mismos filtros del listado (o la selección) y respetando la visibilidad del usuario.
 */
class ExportExpedientesTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $depId;

    private int $serieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1, 'nombres' => 'Zoraida', 'apellidos' => 'Quintero']);
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver Todos', 'web'));
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver', 'web'));

        Schema::disableForeignKeyConstraints();
        $this->depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep XEXP Jurídica', 'cod_organico' => 'XEXD']);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'XEX', 'nom' => 'Contratos']);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(string $numero, string $estado): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => $numero,
            'nombre_expediente' => 'Expediente '.$numero,
            'anio' => 2026,
            'dependencia_id' => $this->depId,
            'serie_trd_id' => $this->serieId,
            'estado' => $estado,
            'fecha_apertura' => '2026-10-18',
            'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function filas(string $query): array
    {
        $contenido = $this->actingAs($this->user)
            ->get('/api/archivo/expedientes/export?format=csv&'.$query)
            ->assertOk()->streamedContent();

        return array_map('str_getcsv', array_values(array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF")))));
    }

    public function test_aplica_los_filtros_del_listado_y_la_seleccion(): void
    {
        $abierto = $this->expediente('2026-XEXD-XEX-0001', 'abierto');
        $this->expediente('2026-XEXD-XEX-0002', 'cerrado');

        // La búsqueda también encuentra por el nombre de la dependencia, como la tabla
        $cerrados = $this->filas('search=XEXP Jur&estado=cerrado');
        $this->assertCount(2, $cerrados);
        $this->assertSame(
            ['2026-XEXD-XEX-0002', 'Expediente 2026-XEXD-XEX-0002', 'Dep XEXP Jurídica', 'XEX Contratos', '', 'cerrado'],
            array_slice($cerrados[1], 0, 6)
        );
        $this->assertSame(['2026-10-18', 'Zoraida Quintero'], array_slice($cerrados[1], 8, 2));

        $seleccion = $this->filas("search=XEXD&ids[]={$abierto->id}");
        $this->assertCount(2, $seleccion);
        $this->assertSame('2026-XEXD-XEX-0001', $seleccion[1][0]);
    }

    public function test_valida_el_formato(): void
    {
        $this->actingAs($this->user)->getJson('/api/archivo/expedientes/export?format=doc')->assertStatus(422);
    }
}
