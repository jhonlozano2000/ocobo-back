<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosMetadata;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosMetadataHistory;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoMetadata;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoMetadataHistory;
use App\Rules\PdfFirmable;
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

    private const HISTORIAL = [
        'enviados' => VentanillaRadicaEnviadosMetadataHistory::class,
        'interno' => VentanillaRadicaInternoMetadataHistory::class,
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

    // Antes cada subida creaba otra fila con archivo_id = 0 y la metadata del digital se duplicaba
    #[DataProvider('modulos')]
    public function test_volver_a_subir_el_digital_actualiza_su_metadata(string $tipo): void
    {
        [$prefijo, $modelo] = self::MODULOS[$tipo];
        $id = $this->radicado($tipo);

        foreach ([self::METADATA, ['descripcion' => 'Oficio corregido', 'palabras_clave' => 'oficio, corregido']] as $metadata) {
            $this->actingAs($this->user)
                ->post("/api/ventanilla/{$prefijo}/{$id}/archivos/digital/upload",
                    ['archivo_digital' => $this->png()] + $metadata, ['Accept' => 'application/json'])
                ->assertSuccessful();
        }

        $filas = $modelo::where('radicado_id', $id)->where('archivo_id', 0)->get();
        $this->assertCount(1, $filas);
        $this->assertSame('Oficio corregido', $filas[0]->descripcion);
        $this->assertSame(['oficio', 'corregido'], $filas[0]->palabras_clave);

        $historial = (self::HISTORIAL[$tipo])::where('metadata_id', $filas[0]->id)->get();
        $this->assertSame(1, $historial->where('tipo_cambio', 'CREACION')->count());
        $cambio = $historial->firstWhere('campo_modificado', 'descripcion');
        $this->assertSame('Oficio firmado', $cambio->valor_anterior);
        $this->assertSame('Oficio corregido', $cambio->valor_nuevo);
    }

    #[DataProvider('modulos')]
    public function test_el_digital_rechaza_un_pdf_protegido(string $tipo): void
    {
        [$prefijo] = self::MODULOS[$tipo];
        $id = $this->radicado($tipo);
        $fpdf = new \FPDF;
        $fpdf->AddPage();
        // Un PDF protegido declara /Encrypt en el trailer: el sello de la firma no se puede estampar
        $pdf = str_replace("trailer\n<<\n", "trailer\n<<\n/Encrypt 1 0 R\n", $fpdf->Output('S'));

        $this->actingAs($this->user)
            ->post("/api/ventanilla/{$prefijo}/{$id}/archivos/digital/upload",
                ['archivo_digital' => UploadedFile::fake()->createWithContent('oficio.pdf', $pdf)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonFragment([PdfFirmable::MENSAJE]);
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
