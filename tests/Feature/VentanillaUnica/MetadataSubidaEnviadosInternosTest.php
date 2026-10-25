<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosMetadata;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoMetadata;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 17.3/17.4: enviados e internos reutilizan los diálogos de subida de recibidos; la metadata que
 * se escribe ahí (descripción y palabras clave, como texto del formulario) queda en la tabla de
 * metadata de su módulo, con las palabras clave como arreglo.
 */
class MetadataSubidaEnviadosInternosTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const MODULOS = [
        'enviados' => ['radica-enviada', VentanillaRadicaEnviadosMetadata::class],
        'interno' => ['radica-interno', VentanillaRadicaInternoMetadata::class],
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('radicados_enviados');
        Storage::fake('radicados_internos');
        Queue::fake();

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Enviada', 'Interna'] as $modulo) {
            foreach (['Subir adjuntos', 'Subir digital', 'Mostrar'] as $accion) {
                $this->user->givePermissionTo(Permission::findOrCreate("Radicar -> Cores. {$modulo} -> {$accion}", 'web'));
            }
        }
        Schema::disableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public static function modulos(): array
    {
        return ['enviados' => ['enviados'], 'internos' => ['interno']];
    }

    private function radicado(string $tipo): int
    {
        $comun = ['usuario_crea' => $this->user->id, 'asunto' => 'Asunto del radicado', 'created_at' => now(), 'updated_at' => now()];

        return $tipo === 'enviados'
            ? DB::table('ventanilla_radica_enviados')->insertGetId($comun + [
                'num_radicado' => 'M17-E-'.uniqid(), 'clasifica_documen_id' => 999999, 'tercero_id' => 1,
                'medio_enviado_id' => 1, 'tipo_respuesta_id' => 1, 'estado_firma' => 'pendiente',
            ])
            : DB::table('ventanilla_radica_internos')->insertGetId($comun + [
                'num_radicado' => 'M17-I-'.uniqid(), 'clasifica_documen_id' => 999999, 'estado_trabajo' => 'RECIBIDO',
            ]);
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('anexo.png', base64_decode(self::PNG));
    }

    private const METADATA = ['descripcion' => '  Oficio firmado  ', 'palabras_clave' => 'oficio, firma , ,oficio,respuesta'];

    #[DataProvider('modulos')]
    public function test_los_adjuntos_guardan_la_metadata_escrita_al_subir(string $tipo): void
    {
        [$prefijo, $modelo] = self::MODULOS[$tipo];
        $id = $this->radicado($tipo);

        $archivoId = $this->actingAs($this->user)
            ->post("/api/ventanilla/{$prefijo}/{$id}/archivos/adjuntos/upload-adjuntos",
                ['archivos' => [$this->png()]] + self::METADATA, ['Accept' => 'application/json'])
            ->assertSuccessful()->json('data.0.id');

        $meta = $modelo::where('archivo_id', $archivoId)->firstOrFail();
        $this->assertSame($id, (int) $meta->radicado_id);
        $this->assertSame('adjunto', $meta->tipo_archivo);
        $this->assertSame('Oficio firmado', $meta->descripcion);
        $this->assertSame(['oficio', 'firma', 'respuesta'], $meta->palabras_clave);
    }

    #[DataProvider('modulos')]
    public function test_el_digital_guarda_la_metadata_escrita_al_subir(string $tipo): void
    {
        [$prefijo, $modelo] = self::MODULOS[$tipo];
        $id = $this->radicado($tipo);

        $this->actingAs($this->user)
            ->post("/api/ventanilla/{$prefijo}/{$id}/archivos/digital/upload",
                ['archivo_digital' => $this->png()] + self::METADATA, ['Accept' => 'application/json'])
            ->assertSuccessful();

        $meta = $modelo::where('radicado_id', $id)->where('tipo_archivo', 'digital')->firstOrFail();
        $this->assertSame('Oficio firmado', $meta->descripcion);
        $this->assertSame(['oficio', 'firma', 'respuesta'], $meta->palabras_clave);
    }

    #[DataProvider('modulos')]
    public function test_sin_metadata_la_descripcion_es_el_asunto(string $tipo): void
    {
        [$prefijo, $modelo] = self::MODULOS[$tipo];
        $id = $this->radicado($tipo);

        $archivoId = $this->actingAs($this->user)
            ->post("/api/ventanilla/{$prefijo}/{$id}/archivos/adjuntos/upload-adjuntos",
                ['archivos' => [$this->png()], 'descripcion' => '', 'palabras_clave' => ''], ['Accept' => 'application/json'])
            ->assertSuccessful()->json('data.0.id');

        $meta = $modelo::where('archivo_id', $archivoId)->firstOrFail();
        $this->assertSame('Asunto del radicado', $meta->descripcion);
        $this->assertNull($meta->palabras_clave);
    }
}
