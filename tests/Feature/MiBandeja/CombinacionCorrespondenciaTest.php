<?php

namespace Tests\Feature\MiBandeja;

use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\OfiArchivo\PlantillaGeneradaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Combinación de correspondencia: las variables del radicado recibido al que responde el documento (número,
 * asunto, fechas y datos del remitente) se llenan en la vista previa del editor y al crear desde una plantilla.
 */
class CombinacionCorrespondenciaTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $yo;

    private int $radicado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->yo = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Ruiz']);

        Schema::disableForeignKeyConstraints();
        $tercero = DB::table('gestion_terceros')->insertGetId([
            'num_docu_nit' => 'TST-CC-'.uniqid(), 'nom_razo_soci' => 'Pedro Gómez', 'tipo' => 'Natural',
            'direccion' => 'Carrera 7 # 8-9', 'email' => 'pedro@prueba.co',
        ]);
        $this->radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'CC-R-0001', 'asunto' => 'Derecho de petición', 'medio_recep_id' => 1,
            'usuario_crea' => $this->yo->id, 'tercero_id' => $tercero, 'fec_docu' => '2026-10-01',
            'created_at' => '2026-10-02 09:00:00', 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function documento(?int $radicado): int
    {
        return DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'radica_reci_id' => $radicado, 'titulo' => 'Respuesta', 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_da_las_variables_con_los_datos_del_radicado_y_del_remitente(): void
    {
        $respuesta = $this->actingAs($this->yo)->getJson(self::URL.'/'.$this->documento($this->radicado).'/combinacion')
            ->assertOk()
            ->assertJsonPath('data.radicado.num_radicado', 'CC-R-0001');

        $valores = collect($respuesta->json('data.variables'))->pluck('valor', 'clave');
        $this->assertSame('CC-R-0001', $valores['radicado_numero']);
        $this->assertSame('Derecho de petición', $valores['radicado_asunto']);
        $this->assertSame('2 de octubre de 2026', $valores['radicado_fecha']);
        $this->assertSame('1 de octubre de 2026', $valores['radicado_fecha_documento']);
        $this->assertSame('Pedro Gómez', $valores['remitente_nombre']);
        $this->assertSame('Carrera 7 # 8-9', $valores['remitente_direccion']);
        $this->assertSame('', $valores['remitente_telefono']);
        $this->assertSame('Ana Ruiz', $valores['usuario_nombre']);

        $grupos = collect($respuesta->json('data.variables'))->pluck('grupo', 'clave');
        $this->assertSame('radicado', $grupos['remitente_correo']);
        $this->assertSame('general', $grupos['fecha']);
    }

    public function test_sin_radicado_las_variables_del_radicado_quedan_vacias_y_otro_usuario_no_entra(): void
    {
        $id = $this->documento(null);
        $respuesta = $this->actingAs($this->yo)->getJson(self::URL."/{$id}/combinacion")
            ->assertOk()
            ->assertJsonPath('data.radicado', null);
        $this->assertSame('', collect($respuesta->json('data.variables'))->firstWhere('clave', 'radicado_numero')['valor']);

        $otro = User::factory()->create(['estado' => 1]);
        $this->actingAs($otro)->getJson(self::URL."/{$id}/combinacion")->assertForbidden();
    }

    public function test_la_plantilla_llena_las_variables_del_radicado_al_crear_el_documento(): void
    {
        $plantilla = new OfiArchivoPlantillaGenerada([
            'contenido' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'variable', 'attrs' => ['clave' => 'radicado_numero']],
                ['type' => 'text', 'text' => ' — '],
                ['type' => 'variable', 'attrs' => ['clave' => 'remitente_nombre', 'mayusculas' => true]],
            ]]]],
            'configuracion' => [],
        ]);

        $resultado = app(PlantillaGeneradaService::class)
            ->paraDocumento($plantilla, $this->yo, VentanillaRadicaReci::find($this->radicado));
        $this->assertSame(
            ['CC-R-0001', ' — ', 'PEDRO GÓMEZ'],
            array_column($resultado['contenido']['content'][0]['content'], 'text')
        );

        $sinRadicado = app(PlantillaGeneradaService::class)->paraDocumento($plantilla, $this->yo);
        $this->assertSame(
            ['type' => 'campo', 'attrs' => ['etiqueta' => 'Número de radicado']],
            $sinRadicado['contenido']['content'][0]['content'][0]
        );
    }
}
