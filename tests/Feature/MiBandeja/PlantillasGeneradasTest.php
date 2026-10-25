<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Configuracion\ConfigLista;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\User;
use App\Services\OfiArchivo\VariablesPlantillaService;
use Database\Seeders\Configuracion\TiposPlantillaSeed;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Plantillas generadas con el editor: el Generador (Gestión de Archivo) las crea, edita con un documento de
 * trabajo y publica; los usuarios crean documentos con ellas (variables llenas), guardan las suyas como
 * personales y usan las cargadas (archivos) para descargar o abrir en el editor.
 */
class PlantillasGeneradasTest extends TestCase
{
    use DatabaseTransactions;

    private const GENERADOR = '/api/archivo/plantillas-generadas';

    private const MIS = '/api/mi-bandeja/comunicaciones-recibidas';

    private User $admin;

    private User $yo;

    private User $otro;

    /** @var array<string, int> codigo => id de los tipos de plantilla */
    private array $tipos;

    /** Archivos de plantillas cargadas creados por la prueba (en el disco real). */
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('otros_archivos');

        $this->admin = User::factory()->create(['estado' => 1]);
        $this->yo = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Ruiz']);
        $this->otro = User::factory()->create(['estado' => 1]);

        foreach (['Ver', 'Crear', 'Editar', 'Eliminar'] as $accion) {
            $this->admin->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Plantillas -> '.$accion, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(TiposPlantillaSeed::class);
        $this->tipos = ConfigLista::where('cod', TiposPlantillaSeed::COD_LISTA)->first()->detalles()->pluck('id', 'codigo')->all();

        foreach ([
            'razon_social_empresa' => 'Alcaldía de Prueba',
            'nit_empresa' => '900.1',
            'direccion_empresa' => 'Calle 1',
            'telefono_empresa' => null,
            'correo_electronico_empresa' => 'c@prueba.gov.co',
            'web_empresa' => null,
            'divi_poli_id_empresa' => null,
            'logo_empresa' => null,
        ] as $clave => $valor) {
            DB::table('config_varias')->updateOrInsert(['clave' => $clave], ['valor' => $valor]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $ruta) {
            @unlink($ruta);
        }
        parent::tearDown();
    }

    private function parrafo(array ...$inline): array
    {
        return ['type' => 'paragraph', 'content' => $inline];
    }

    private function texto(string $texto, array $marcas = []): array
    {
        return ['type' => 'text', 'text' => $texto] + ($marcas ? ['marks' => $marcas] : []);
    }

    /**
     * Plantillas generadas que ve el usuario autenticado, por id. La BD puede traer las institucionales de base
     * (PlantillasGeneradasBaseSeed), así que las pruebas miran solo las que crean.
     */
    private function generadasVisibles(): Collection
    {
        return collect($this->getJson(self::MIS.'/plantillas')->assertOk()->json('data.generadas'))->keyBy('id');
    }

    private function contenidoDe(int $documentoId): array
    {
        return json_decode(DB::table('mi_bandeja_temp_reci_contenido')->where('documento_id', $documentoId)->value('contenido_yjs'), true);
    }

    private function escribir(int $documentoId, array $contenido): void
    {
        DB::table('mi_bandeja_temp_reci_contenido')->where('documento_id', $documentoId)
            ->update(['contenido_yjs' => json_encode($contenido), 'updated_at' => now()->addSecond()]);
    }

    public function test_el_generador_crea_edita_y_publica_una_plantilla_institucional(): void
    {
        $id = $this->actingAs($this->admin)->postJson(self::GENERADOR, [
            'nombre' => 'Resolución general',
            'tipo_id' => $this->tipos['RESOLUCION'],
        ])->assertCreated()
            ->assertJsonPath('data.activo', false)
            ->assertJsonPath('data.tipo.nombre', 'Resolución')
            ->json('data.id');

        // Se edita en el editor con un documento de trabajo, que no aparece en Mis Documentos
        $documentoId = $this->postJson(self::GENERADOR."/{$id}/editar")->assertOk()->json('data.documento_id');
        $this->assertSame($documentoId, $this->postJson(self::GENERADOR."/{$id}/editar")->json('data.documento_id'));
        $this->getJson(self::MIS.'/documentos')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::MIS."/documentos/{$documentoId}")->assertOk()
            ->assertJsonPath('data.plantilla_generada.id', $id)
            ->assertJsonPath('data.plantilla_generada.institucional', true);

        // Al guardar pasan el contenido (sin comentarios) y la página
        $this->escribir($documentoId, ['type' => 'doc', 'content' => [
            $this->parrafo(
                $this->texto('Fecha: ', [['type' => 'bold']]),
                ['type' => 'variable', 'attrs' => ['clave' => 'fecha', 'mayusculas' => false]],
                $this->texto(' revisar', [['type' => 'comentario', 'attrs' => ['id' => 'c1']]]),
            ),
        ]]);
        DB::table('mi_bandeja_temp_reci_documentos')->where('id', $documentoId)->update(['tamano_papel' => 'legal']);
        $this->postJson(self::MIS."/documentos/{$documentoId}/guardar-plantilla")->assertOk();

        $plantilla = OfiArchivoPlantillaGenerada::find($id);
        $this->assertSame('legal', $plantilla->configuracion['tamano_papel']);
        $this->assertEquals([
            $this->texto('Fecha: ', [['type' => 'bold']]),
            ['type' => 'variable', 'attrs' => ['clave' => 'fecha', 'mayusculas' => false]],
            $this->texto(' revisar'),
        ], $plantilla->contenido['content'][0]['content']);

        // Sin publicar no la ve nadie al crear documentos; publicada, sí
        $this->assertArrayNotHasKey($id, $this->actingAs($this->yo)->generadasVisibles()->all());
        $this->actingAs($this->admin)->patchJson(self::GENERADOR."/{$id}", ['activo' => true])->assertOk();
        $publicada = $this->actingAs($this->yo)->generadasVisibles()[$id];
        $this->assertSame('Resolución general', $publicada['nombre']);
        $this->assertTrue($publicada['institucional']);
        $this->assertFalse($publicada['propia']);

        $this->actingAs($this->admin)->getJson(self::GENERADOR.'?tipo_id='.$this->tipos['RESOLUCION'])->assertOk()
            ->assertJsonPath('pagination.total', 1);
        // Un tipo que no traen las plantillas de base (oficio, memorando, circular y acta)
        $this->getJson(self::GENERADOR.'?tipo_id='.$this->tipos['CERTIFICADO'])->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_al_abrir_otra_vez_el_documento_de_trabajo_se_pone_al_dia_si_la_plantilla_cambio(): void
    {
        $plantilla = OfiArchivoPlantillaGenerada::create([
            'user_id' => $this->admin->id, 'nombre' => 'Informe', 'institucional' => true,
            'contenido' => ['type' => 'doc', 'content' => [$this->parrafo($this->texto('v1'))]],
        ]);
        $documentoId = $this->actingAs($this->admin)->postJson(self::GENERADOR."/{$plantilla->id}/editar")->json('data.documento_id');

        // Otro editor la guardó después: al volver a abrirla se trae lo nuevo
        $plantilla->forceFill(['contenido' => ['type' => 'doc', 'content' => [$this->parrafo($this->texto('v2'))]], 'updated_at' => now()->addMinute()])->save();
        $this->postJson(self::GENERADOR."/{$plantilla->id}/editar")->assertOk();

        $this->assertSame('v2', $this->contenidoDe($documentoId)['content'][0]['content'][0]['text']);
    }

    public function test_sin_permiso_no_se_usa_el_generador_ni_se_guardan_institucionales(): void
    {
        $plantilla = OfiArchivoPlantillaGenerada::create([
            'user_id' => $this->admin->id, 'nombre' => 'Oficio', 'institucional' => true,
            'contenido' => ['type' => 'doc', 'content' => []],
        ]);

        $this->actingAs($this->yo)->getJson(self::GENERADOR)->assertForbidden();
        $this->postJson(self::GENERADOR, ['nombre' => 'Mía'])->assertForbidden();
        $this->postJson(self::GENERADOR."/{$plantilla->id}/editar")->assertForbidden();
        $this->deleteJson(self::GENERADOR."/{$plantilla->id}")->assertForbidden();
        $this->deleteJson(self::MIS."/plantillas/{$plantilla->id}")->assertNotFound();

        // Aunque tuviera un documento de trabajo, guardar una institucional pide el permiso
        $documentoId = DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $this->yo->id, 'titulo' => 'Oficio', 'estado' => 'borrador',
            'plantilla_generada_id' => $plantilla->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson(self::MIS."/documentos/{$documentoId}/guardar-plantilla")->assertForbidden();
    }

    public function test_guardar_como_plantilla_crea_una_personal_que_solo_ve_su_autor(): void
    {
        $documentoId = $this->actingAs($this->yo)->postJson(self::MIS.'/documentos', ['titulo' => 'Mi informe'])->json('data.id');
        $this->escribir($documentoId, ['type' => 'doc', 'content' => [
            $this->parrafo(
                $this->texto('Conservar'),
                $this->texto(' borrado', [['type' => 'eliminacion', 'attrs' => ['autor' => 1]]]),
                $this->texto(' sugerido', [['type' => 'insercion', 'attrs' => ['autor' => 1]]]),
            ),
        ]]);

        $id = $this->postJson(self::MIS.'/plantillas', [
            'documento_id' => $documentoId, 'nombre' => 'Mi informe base', 'tipo_id' => $this->tipos['INFORME'],
        ])->assertCreated()
            ->assertJsonPath('data.institucional', false)
            ->assertJsonPath('data.propia', true)
            ->json('data.id');

        $this->assertEquals(
            [$this->texto('Conservar'), $this->texto(' sugerido')],
            OfiArchivoPlantillaGenerada::find($id)->contenido['content'][0]['content']
        );
        $this->assertArrayHasKey($id, $this->generadasVisibles()->all());

        // Los demás no la ven ni la pueden usar, editar o borrar
        $this->assertArrayNotHasKey($id, $this->actingAs($this->otro)->generadasVisibles()->all());
        $this->postJson(self::MIS.'/documentos', ['titulo' => 'Copia', 'plantilla_id' => $id])
            ->assertUnprocessable()->assertJsonValidationErrors(['plantilla_id']);
        $this->patchJson(self::MIS."/plantillas/{$id}", ['nombre' => 'Robada'])->assertNotFound();
        $this->postJson(self::MIS."/plantillas/{$id}/editar")->assertNotFound();
        $this->deleteJson(self::MIS."/plantillas/{$id}")->assertNotFound();

        // Ni guardar como plantilla un documento ajeno
        $this->postJson(self::MIS.'/plantillas', ['documento_id' => $documentoId, 'nombre' => 'Ajena'])->assertForbidden();

        // Su autor la renombra, la edita en el editor y la borra (con su documento de trabajo)
        $this->actingAs($this->yo)->patchJson(self::MIS."/plantillas/{$id}", ['nombre' => 'Informe mensual'])->assertOk()
            ->assertJsonPath('data.nombre', 'Informe mensual');
        $trabajo = $this->postJson(self::MIS."/plantillas/{$id}/editar")->assertOk()->json('data.documento_id');
        $this->postJson(self::MIS."/documentos/{$trabajo}/guardar-plantilla")->assertOk();
        $this->deleteJson(self::MIS."/plantillas/{$id}")->assertOk();
        $this->assertSoftDeleted('ofi_archivo_plantillas_generadas', ['id' => $id]);
        $this->assertDatabaseMissing('mi_bandeja_temp_reci_documentos', ['id' => $trabajo]);
    }

    public function test_al_crear_el_documento_se_llenan_las_variables(): void
    {
        $plantilla = OfiArchivoPlantillaGenerada::create([
            'user_id' => $this->admin->id, 'nombre' => 'Constancia', 'institucional' => true, 'activo' => true,
            'contenido' => ['type' => 'doc', 'content' => [$this->parrafo(
                ['type' => 'variable', 'attrs' => ['clave' => 'entidad_nombre', 'mayusculas' => true], 'marks' => [['type' => 'bold']]],
                $this->texto(' certifica que '),
                ['type' => 'variable', 'attrs' => ['clave' => 'usuario_nombre']],
                $this->texto(' trabaja en '),
                ['type' => 'variable', 'attrs' => ['clave' => 'usuario_dependencia']],
            )]],
            'configuracion' => [
                'tamano_papel' => 'a4',
                'configuracion_footer' => ['habilitado' => true, 'elementos' => ['texto_libre'], 'texto_libre' => '{entidad_direccion} · Tel. {entidad_telefono} · {entidad_correo}'],
            ],
        ]);

        $id = $this->actingAs($this->yo)->postJson(self::MIS.'/documentos', ['titulo' => 'Constancia laboral', 'plantilla_id' => $plantilla->id])
            ->assertCreated()->json('data.id');

        $this->assertEquals([
            $this->texto('ALCALDÍA DE PRUEBA', [['type' => 'bold']]),
            $this->texto(' certifica que '),
            $this->texto('Ana Ruiz'),
            $this->texto(' trabaja en '),
            ['type' => 'campo', 'attrs' => ['etiqueta' => 'Dependencia del usuario']],
        ], $this->contenidoDe($id)['content'][0]['content']);

        $documento = DB::table('mi_bandeja_temp_reci_documentos')->find($id);
        $this->assertSame('a4', $documento->tamano_papel);
        $this->assertNull($documento->plantilla_generada_id);
        $this->assertSame('Calle 1 · c@prueba.gov.co', json_decode($documento->configuracion_footer, true)['texto_libre']);
    }

    public function test_el_texto_del_pie_quita_los_datos_que_faltan_con_su_separador(): void
    {
        $variables = app(VariablesPlantillaService::class);

        $this->assertSame('Tel. 123', $variables->resolverTexto('{entidad_direccion} · Tel. {entidad_telefono}', ['entidad_telefono' => '123']));
        $this->assertSame('Fijo · Calle 1 {otra}', $variables->resolverTexto('Fijo · {entidad_direccion} {otra}', ['entidad_direccion' => 'Calle 1']));
        $this->assertSame('', $variables->resolverTexto('{entidad_web}', []));
    }

    /** Plantilla cargada con un archivo real en el disco de plantillas. */
    private function cargada(string $extension, array $atributos = []): OfiArchivoPlantillaDocumento
    {
        $nombre = 'prueba-'.uniqid().'.'.$extension;
        $ruta = storage_path('app/plantillas/'.$nombre);
        @mkdir(dirname($ruta), 0777, true);
        $this->archivos[] = $ruta;

        if ($extension === 'docx') {
            $word = new PhpWord;
            $word->addSection()->addText('Texto de la plantilla cargada');
            IOFactory::createWriter($word, 'Word2007')->save($ruta);
        } else {
            file_put_contents($ruta, '%PDF-1.4 prueba');
        }

        return OfiArchivoPlantillaDocumento::create($atributos + [
            'nombre_original' => 'Formato de '.$extension.'.'.$extension,
            'nombre_archivo' => $nombre,
            'ruta_completa' => $nombre,
            'peso' => 1,
            'extension' => $extension,
            'mime_type' => $extension === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf',
            'hash_seguridad' => hash_file('sha256', $ruta),
            'version' => '1.0',
            'activo' => true,
            'user_crea_id' => $this->admin->id,
        ]);
    }

    public function test_las_cargadas_se_listan_con_su_tipo_se_descargan_y_las_de_word_se_abren_en_el_editor(): void
    {
        $word = $this->cargada('docx', ['categoria_id' => $this->tipos['CERTIFICADO']]);
        $pdf = $this->cargada('pdf');
        $inactiva = $this->cargada('docx', ['activo' => false]);

        $cargadas = collect($this->actingAs($this->yo)->getJson(self::MIS.'/plantillas')->assertOk()->json('data.cargadas'))->keyBy('id');
        $this->assertSame($this->tipos['CERTIFICADO'], $cargadas[$word->id]['tipo_id']);
        $this->assertTrue($cargadas[$word->id]['editable']);
        $this->assertFalse($cargadas[$pdf->id]['editable']);
        $this->assertArrayNotHasKey($inactiva->id, $cargadas->all());

        $this->get(self::MIS."/plantillas-cargadas/{$pdf->id}/descargar")->assertOk();
        $this->getJson(self::MIS."/plantillas-cargadas/{$inactiva->id}/descargar")->assertNotFound();
        $this->postJson(self::MIS."/plantillas-cargadas/{$pdf->id}/usar")->assertStatus(400);

        $documentoId = $this->postJson(self::MIS."/plantillas-cargadas/{$word->id}/usar")->assertCreated()->json('data.documento_id');
        $this->assertDatabaseHas('mi_bandeja_temp_reci_documentos', ['id' => $documentoId, 'user_id' => $this->yo->id, 'titulo' => 'Formato de docx']);
        $this->assertStringContainsString('Texto de la plantilla cargada', json_encode($this->contenidoDe($documentoId), JSON_UNESCAPED_UNICODE));
    }

    public function test_el_cargue_valida_el_tipo_y_filtra_por_el(): void
    {
        $word = $this->cargada('docx', ['categoria_id' => $this->tipos['ACTA']]);
        $this->cargada('pdf');
        Permission::findOrCreate('Gestion de Archivo -> Plantillas -> Ver', 'web');

        $this->actingAs($this->admin)->getJson('/api/archivo/plantillas?categoria_id='.$this->tipos['ACTA'])->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.tipo.nombre', 'Acta');

        $this->postJson("/api/archivo/plantillas/{$word->id}", ['categoria_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors(['categoria_id']);
        $this->postJson("/api/archivo/plantillas/{$word->id}", ['categoria_id' => $this->tipos['OFICIO']])->assertOk();
        $this->assertSame($this->tipos['OFICIO'], $word->fresh()->categoria_id);
    }
}
