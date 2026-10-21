<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsMetadata;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M17-05: los archivos de una PQRS viven en su radicado recibido; la metadata capturada al
 * subirlos se guarda en ventanilla_pqrs_metadata, y solo para la PQRS de ese radicado.
 */
class MetadataArchivosPqrsTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private User $user;

    private int $radicadoId;

    private int $pqrsId;

    private int $pqrsAjenaId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('radicados_recibidos');
        Queue::fake();

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Subir adjuntos', 'Subir digital', 'Mostrar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate("Radicar -> Cores. Recibida -> {$accion}", 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $radicado = fn (string $sufijo) => DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M1705-'.$sufijo.'-'.uniqid(), 'medio_recep_id' => 1,
            'usuario_crea' => $this->user->id, 'asunto' => 'PQRS con anexos',
        ]);
        $this->radicadoId = $radicado('A');
        $otroRadicado = $radicado('B');
        $pqrs = fn (int $radicadoId) => DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $radicadoId, 'tipo_pqrs_id' => 1, 'fecha_vencimiento' => '2026-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pqrsId = $pqrs($this->radicadoId);
        $this->pqrsAjenaId = $pqrs($otroRadicado);
        Schema::enableForeignKeyConstraints();
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('anexo.png', base64_decode(self::PNG));
    }

    private function metadata(int $pqrsId): array
    {
        return ['tipo' => 'pqrs', 'pqrs_id' => $pqrsId, 'descripcion' => 'Anexo del peticionario', 'palabras_clave' => 'peticion, anexo'];
    }

    public function test_los_adjuntos_guardan_la_metadata_en_la_pqrs(): void
    {
        $id = $this->actingAs($this->user)
            ->post("/api/ventanilla/radica-recibida/{$this->radicadoId}/archivos/adjuntos/upload-adjuntos",
                ['archivos' => [$this->png()]] + $this->metadata($this->pqrsId), ['Accept' => 'application/json'])
            ->assertOk()->json('data.0.id');

        $meta = VentanillaPqrsMetadata::where('archivo_id', $id)->firstOrFail();
        $this->assertSame($this->pqrsId, (int) $meta->pqrs_id);
        $this->assertSame('Anexo del peticionario', $meta->descripcion);
        $this->assertSame('adjunto', $meta->tipo_archivo);
        $this->assertFalse(DB::table('ventanilla_radica_reci_metadata')->where('archivo_id', $id)->exists());
    }

    public function test_el_digital_guarda_la_metadata_sin_archivo_id(): void
    {
        $this->actingAs($this->user)
            ->post("/api/ventanilla/radica-recibida/{$this->radicadoId}/archivos/digital/upload",
                ['archivo_digital' => $this->png()] + $this->metadata($this->pqrsId), ['Accept' => 'application/json'])
            ->assertOk();

        $meta = VentanillaPqrsMetadata::where('pqrs_id', $this->pqrsId)->firstOrFail();
        $this->assertNull($meta->archivo_id);
        $this->assertSame('digital', $meta->tipo_archivo);
        $this->assertSame('Anexo del peticionario', $meta->descripcion);
    }

    public function test_rechaza_una_pqrs_de_otro_radicado_sin_guardar_el_archivo(): void
    {
        $this->actingAs($this->user)
            ->post("/api/ventanilla/radica-recibida/{$this->radicadoId}/archivos/adjuntos/upload-adjuntos",
                ['archivos' => [$this->png()]] + $this->metadata($this->pqrsAjenaId), ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->actingAs($this->user)
            ->post("/api/ventanilla/radica-recibida/{$this->radicadoId}/archivos/digital/upload",
                ['archivo_digital' => $this->png()] + $this->metadata($this->pqrsAjenaId), ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertFalse(DB::table('ventanilla_radica_reci_archivos')->where('radicado_id', $this->radicadoId)->exists());
        $this->assertNull(DB::table('ventanilla_radica_reci')->where('id', $this->radicadoId)->value('archivo_digital'));
        $this->assertFalse(VentanillaPqrsMetadata::whereIn('pqrs_id', [$this->pqrsId, $this->pqrsAjenaId])->exists());
    }
}
