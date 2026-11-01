<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoEliminacion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Transferencias y eliminaciones se exportaban en el navegador con lo cargado (y la eliminación con columnas
 * de transferencia); ahora el back arma cada archivo con los filtros del listado o la selección.
 */
class ExportTransferenciasTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $depId;

    private int $serieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1, 'nombres' => 'Zoraida', 'apellidos' => 'Quintero']);
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Transferencias -> Ver', 'web'));
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Eliminaciones -> Ver', 'web'));

        Schema::disableForeignKeyConstraints();
        $this->depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep XTRF', 'cod_organico' => 'XTRF']);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'XTR', 'nom' => 'Serie XTRF']);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(string $numero): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => $numero, 'nombre_expediente' => 'Expediente '.$numero, 'anio' => 2026,
            'dependencia_id' => $this->depId, 'serie_trd_id' => $this->serieId, 'estado' => 'cerrado',
            'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $this->user->id,
        ]);
    }

    private function transferencia(string $numero, string $estado, string $origen = 'Archivo de gestión'): OfiArchivoTransferencia
    {
        return OfiArchivoTransferencia::create([
            'expediente_id' => $this->expediente($numero)->id, 'tipo' => 'primaria', 'origen' => $origen, 'destino' => 'Archivo central',
            'responsable_origen_id' => $this->user->id, 'fecha_transferencia' => '2026-10-20 08:00:00', 'estado' => $estado,
            'usuario_registro_id' => $this->user->id,
        ]);
    }

    private function filas(string $url): array
    {
        $contenido = $this->actingAs($this->user)->get($url)->assertOk()->streamedContent();

        return array_map('str_getcsv', array_values(array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF")))));
    }

    public function test_transferencias_aplica_filtros_y_seleccion(): void
    {
        $pendiente = $this->transferencia('XTRF-0001', 'pendiente');
        $this->transferencia('XTRF-0002', 'aprobada');
        $this->transferencia('XTRF-0003', 'pendiente', 'Oficina XTRF Norte');

        // Como la tabla, la búsqueda también cubre el origen y el destino
        $porOrigen = $this->filas('/api/archivo/transferencias/export?format=csv&search=XTRF Norte');
        $this->assertCount(2, $porOrigen);
        $this->assertSame('XTRF-0003', $porOrigen[1][0]);

        $pendientes = $this->filas('/api/archivo/transferencias/export?format=csv&search=XTRF-000&estado=pendiente');
        $this->assertSame('Expediente', $pendientes[0][0]);
        $this->assertEqualsCanonicalizing(['XTRF-0001', 'XTRF-0003'], array_column(array_slice($pendientes, 1), 0));

        $seleccion = $this->filas("/api/archivo/transferencias/export?format=csv&ids[]={$pendiente->id}");
        $this->assertCount(2, $seleccion);
        $this->assertSame(
            ['XTRF-0001', 'Expediente XTRF-0001', 'primaria', 'Archivo de gestión', 'Archivo central', 'Zoraida Quintero', '', '', '2026-10-20', 'pendiente'],
            $seleccion[1]
        );
    }

    public function test_eliminaciones_aplica_busqueda(): void
    {
        foreach (['XELM-0001', 'XELM-0002'] as $numero) {
            OfiArchivoEliminacion::create([
                'expediente_id' => $this->expediente($numero)->id, 'fecha' => '2026-10-21 09:00:00', 'responsable_ids' => [$this->user->id],
                'metodo' => 'borrado_seguro', 'aprobado_por_id' => $this->user->id, 'usuario_registro_id' => $this->user->id,
            ]);
        }

        $filas = $this->filas('/api/archivo/eliminaciones/export?format=csv&search=XELM-0002');

        $this->assertSame(['Expediente', 'Nombre del expediente', 'Fecha', 'Método', 'Aprobado por', 'Registrado por'], $filas[0]);
        $this->assertSame(['XELM-0002', 'Expediente XELM-0002', '2026-10-21', 'borrado_seguro', 'Zoraida Quintero', 'Zoraida Quintero'], $filas[1]);
        $this->assertCount(2, $filas);
    }

    public function test_valida_formato_y_permiso(): void
    {
        $this->actingAs($this->user)->getJson('/api/archivo/transferencias/export?format=doc')->assertStatus(422);

        $sinPermiso = User::factory()->create(['estado' => 1]);
        $this->actingAs($sinPermiso)->getJson('/api/archivo/eliminaciones/export?format=csv')->assertForbidden();
    }
}
