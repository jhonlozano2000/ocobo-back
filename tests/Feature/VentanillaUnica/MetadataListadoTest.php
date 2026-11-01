<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * En /metadata había que escribir el ID del archivo, que el usuario no conoce; el listado le muestra los
 * adjuntos con metadata de los radicados que puede ver, con buscador.
 */
class MetadataListadoTest extends TestCase
{
    use DatabaseTransactions;

    private User $creador;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('Radicar -> Cores. Recibida -> Mostrar', 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo('Radicar -> Cores. Recibida -> Mostrar');
        $this->ajeno->givePermissionTo('Radicar -> Cores. Recibida -> Mostrar');

        Schema::disableForeignKeyConstraints();
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'XMETL-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->creador->id, 'asunto' => 'Derecho de petición XMETL',
        ]);
        foreach (['anexo-xmetl.pdf', 'soporte-xmetl.pdf'] as $nombre) {
            $archivo = DB::table('ventanilla_radica_reci_archivos')->insertGetId([
                'radicado_id' => $radicado, 'subido_por' => $this->creador->id, 'archivo' => 'x/'.$nombre, 'nom_origi' => $nombre,
            ]);
            DB::table('ventanilla_radica_reci_metadata')->insert([
                'archivo_id' => $archivo, 'radicado_id' => $radicado, 'descripcion' => 'Descripción '.$nombre, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Documento digital (centinela 0): no se edita desde la pantalla
        DB::table('ventanilla_radica_reci_metadata')->insert([
            'archivo_id' => 0, 'radicado_id' => $radicado, 'descripcion' => 'Digital XMETL', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    public function test_lista_los_adjuntos_visibles_con_buscador(): void
    {
        $todos = $this->actingAs($this->creador)->getJson('/api/ventanilla/metadata/listado/reci?search=XMETL')->assertOk();
        $this->assertSame(2, $todos->json('pagination.total'));
        $this->assertEqualsCanonicalizing(['anexo-xmetl.pdf', 'soporte-xmetl.pdf'], array_column($todos->json('data'), 'nombre_archivo'));
        $this->assertSame('Derecho de petición XMETL', $todos->json('data.0.asunto'));

        $porArchivo = $this->actingAs($this->creador)->getJson('/api/ventanilla/metadata/listado/reci?search=soporte-xmetl');
        $this->assertSame(1, $porArchivo->json('pagination.total'));
        $this->assertSame('soporte-xmetl.pdf', $porArchivo->json('data.0.nombre_archivo'));

        $ajeno = $this->actingAs($this->ajeno)->getJson('/api/ventanilla/metadata/listado/reci?search=XMETL')->assertOk();
        $this->assertSame(0, $ajeno->json('pagination.total'));
    }

    public function test_exige_el_permiso_del_tipo(): void
    {
        $this->actingAs($this->creador)->getJson('/api/ventanilla/metadata/listado/enviados')->assertForbidden();
    }
}
