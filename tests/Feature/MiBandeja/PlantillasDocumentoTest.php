<?php

namespace Tests\Feature\MiBandeja;

use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\User;
use Database\Seeders\Configuracion\PlantillasGeneradasBaseSeed;
use Database\Seeders\Configuracion\TiposPlantillaSeed;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Plantillas institucionales de base (PlantillasGeneradasBaseSeed): al crear un documento como oficio, acta,
 * memorando o circular empieza con el membrete de la entidad y los datos de quien lo crea (variables ya
 * llenas), en carta con los márgenes de la GTC 185 y el pie de contacto.
 */
class PlantillasDocumentoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $yo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->yo = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Ruiz']);

        Storage::fake('otros_archivos');
        Storage::disk('otros_archivos')->put('logo.png', UploadedFile::fake()->image('logo.png', 20, 20)->get());

        foreach ([
            'razon_social_empresa' => 'Alcaldía de Prueba',
            'nit_empresa' => '800.000.001-1',
            'direccion_empresa' => 'Calle 1 # 2-3',
            'telefono_empresa' => '6012345678',
            'correo_electronico_empresa' => 'contacto@prueba.gov.co',
            'web_empresa' => null,
            'divi_poli_id_empresa' => null,
            'logo_empresa' => 'logo.png',
        ] as $clave => $valor) {
            DB::table('config_varias')->updateOrInsert(['clave' => $clave], ['valor' => $valor]);
        }

        $this->seed(TiposPlantillaSeed::class);
        $this->seed(PlantillasGeneradasBaseSeed::class);
    }

    private function crear(array $datos)
    {
        return $this->actingAs($this->yo)->postJson(self::URL, ['titulo' => 'Documento de prueba', ...$datos]);
    }

    /** Documento creado desde la plantilla base de ese nombre. */
    private function desde(string $nombre)
    {
        $plantilla = OfiArchivoPlantillaGenerada::where('institucional', true)->whereNull('user_id')->where('nombre', $nombre)->firstOrFail();

        return $this->crear(['plantilla_id' => $plantilla->id]);
    }

    private function contenido(int $id): array
    {
        return json_decode(DB::table('mi_bandeja_temp_reci_contenido')->where('documento_id', $id)->value('contenido_yjs'), true);
    }

    /** Texto plano de los nodos del contenido, para buscar frases sin depender de la estructura. */
    private function textos(array $nodo): string
    {
        return ($nodo['text'] ?? '').' '.implode(' ', array_map(fn ($hijo) => $this->textos($hijo), $nodo['content'] ?? []));
    }

    /** Campos por completar del contenido y textos sueltos que aún tengan corchetes. */
    private function campos(array $nodo, array &$corchetes = []): array
    {
        if (($nodo['type'] ?? null) === 'campo') {
            return [$nodo['attrs']['etiqueta']];
        }
        if (str_contains($nodo['text'] ?? '', '[')) {
            $corchetes[] = $nodo['text'];
        }

        return array_merge([], ...array_map(fn ($hijo) => $this->campos($hijo, $corchetes), $nodo['content'] ?? []));
    }

    public function test_un_oficio_empieza_con_membrete_firma_y_pagina_institucional(): void
    {
        $id = $this->desde('Oficio')->assertCreated()->json('data.id');

        $documento = DB::table('mi_bandeja_temp_reci_documentos')->find($id);
        $this->assertSame('carta', $documento->tamano_papel);
        $this->assertEquals(['superior' => 30, 'inferior' => 25, 'izquierdo' => 30, 'derecho' => 20], json_decode($documento->margenes, true));
        $this->assertEquals([
            'habilitado' => true,
            'elementos' => ['texto_libre', 'pagina'],
            'texto_libre' => 'Calle 1 # 2-3 · Tel. 6012345678 · contacto@prueba.gov.co',
        ], json_decode($documento->configuracion_footer, true));

        // El logo va en el encabezado, que lo repite en cada hoja; no en el cuerpo
        $this->assertEquals(['habilitado' => true, 'elementos' => ['logo']], json_decode($documento->configuracion_header, true));
        $contenido = $this->contenido($id);
        $this->assertSame('doc', $contenido['type']);
        $this->assertNotContains('image', array_column($contenido['content'], 'type'));

        $texto = $this->textos($contenido);
        $this->assertStringContainsString('ALCALDÍA DE PRUEBA', $texto);
        $this->assertMatchesRegularExpression('/NIT\s+800\.000\.001-1/u', $texto);
        $this->assertStringContainsString('Asunto:', $texto);
        $this->assertStringContainsString('ANA RUIZ', $texto);
    }

    public function test_el_acta_trae_asistentes_orden_del_dia_y_compromisos(): void
    {
        $id = $this->desde('Acta')->assertCreated()->json('data.id');

        $contenido = $this->contenido($id);
        $tipos = array_column($contenido['content'], 'type');
        $this->assertSame(3, count(array_keys($tipos, 'table')));
        $this->assertContains('orderedList', $tipos);

        $texto = $this->textos($contenido);
        $this->assertStringContainsString('ACTA No.', $texto);
        $this->assertContains('número', $this->campos($contenido));
        $this->assertStringContainsString('Orden del día', $texto);
        $this->assertStringContainsString('Compromisos', $texto);
    }

    public function test_memorando_y_circular_identifican_al_remitente(): void
    {
        foreach (['Memorando' => 'MEMORANDO', 'Circular' => 'CIRCULAR No.'] as $plantilla => $titulo) {
            $id = $this->desde($plantilla)->assertCreated()->json('data.id');
            $texto = $this->textos($this->contenido($id));

            $this->assertStringContainsString($titulo, $texto);
            $this->assertStringContainsString('PARA:', $texto);
            $this->assertStringContainsString('ASUNTO:', $texto);
        }
        $this->assertMatchesRegularExpression('/DE:\s+Ana Ruiz/u', $this->textos($this->contenido($this->desde('Memorando')->json('data.id'))));
    }

    public function test_las_plantillas_base_quedan_publicadas_y_clasificadas_y_el_seeder_es_idempotente(): void
    {
        $this->seed(PlantillasGeneradasBaseSeed::class);

        $base = OfiArchivoPlantillaGenerada::where('institucional', true)->whereNull('user_id')->with('tipo')->get();
        $this->assertSame(4, $base->count());
        $this->assertTrue($base->every->activo);
        $this->assertEqualsCanonicalizing(['Acta', 'Circular', 'Memorando', 'Oficio'], $base->pluck('tipo.nombre')->all());
    }

    public function test_lo_que_falta_por_completar_va_en_campos_y_no_en_corchetes(): void
    {
        $id = $this->desde('Oficio')->assertCreated()->json('data.id');
        $corchetes = [];
        $campos = $this->campos($this->contenido($id), $corchetes);

        // La ciudad de la entidad y el cargo y la dependencia de quien firma no están configurados: también
        // quedan por completar
        $this->assertSame([
            'Ciudad', 'Nombre del destinatario', 'Cargo', 'Entidad', 'Dirección', 'Ciudad', 'Asunto',
            'Escriba aquí el texto del oficio.', 'Cargo del usuario', 'Dependencia del usuario',
        ], $campos);
        $this->assertSame([], $corchetes);

        foreach (['Acta', 'Memorando', 'Circular'] as $plantilla) {
            $corchetes = [];
            $id = $this->desde($plantilla)->assertCreated()->json('data.id');
            $this->assertNotEmpty($this->campos($this->contenido($id), $corchetes));
            $this->assertSame([], $corchetes, $plantilla);
        }
    }

    public function test_sin_logo_ni_datos_de_contacto_el_encabezado_y_el_pie_no_quedan_vacios(): void
    {
        Storage::disk('otros_archivos')->put('logo.png', 'no soy una imagen');
        DB::table('config_varias')->whereIn('clave', ['direccion_empresa', 'telefono_empresa', 'correo_electronico_empresa'])
            ->update(['valor' => null]);

        $id = $this->desde('Memorando')->assertCreated()->json('data.id');

        $documento = DB::table('mi_bandeja_temp_reci_documentos')->find($id);
        $this->assertEquals(['habilitado' => false, 'elementos' => []], json_decode($documento->configuracion_header, true));
        $this->assertEquals(['habilitado' => true, 'elementos' => ['pagina'], 'texto_libre' => ''], json_decode($documento->configuracion_footer, true));
    }

    public function test_sin_plantilla_el_documento_queda_en_blanco_y_rechaza_plantillas_desconocidas(): void
    {
        $id = $this->crear([])->assertCreated()->json('data.id');
        $this->assertSame([], $this->contenido($id));

        $this->crear(['plantilla_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plantilla_id']);
    }
}
