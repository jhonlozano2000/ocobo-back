<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Los archivos de una PQRS se gestionan por las rutas de Recibida. El rol Radicador PQRSF solo tiene
 * los permisos «Radicar -> PQRSF -> …»: con ellos debe poder gestionar los archivos de radicados con
 * PQRS, pero no los de un recibido sin PQRS.
 */
class PermisoArchivosPqrsTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private User $user;

    private int $conPqrs;

    private int $sinPqrs;

    private int $pqrsId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('radicados_recibidos');
        Queue::fake();

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Subir adjuntos', 'Eliminar adjuntos', 'Subir digital', 'Mostrar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate("Radicar -> PQRSF -> {$accion}", 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $radicado = fn (string $sufijo) => DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'PERM-'.$sufijo.'-'.uniqid(), 'medio_recep_id' => 1,
            'usuario_crea' => $this->user->id, 'asunto' => 'Archivos de PQRS',
        ]);
        $this->conPqrs = $radicado('A');
        $this->sinPqrs = $radicado('B');
        $this->pqrsId = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $this->conPqrs, 'tipo_pqrs_id' => 1, 'fecha_vencimiento' => '2026-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function subirAdjunto(int $radicadoId, array $extra = [])
    {
        return $this->actingAs($this->user)->post(
            "/api/ventanilla/radica-recibida/{$radicadoId}/archivos/adjuntos/upload-adjuntos",
            ['archivos' => [UploadedFile::fake()->createWithContent('anexo.png', base64_decode(self::PNG))]] + $extra,
            ['Accept' => 'application/json']
        );
    }

    public function test_con_permiso_pqrsf_gestiona_los_archivos_de_la_pqrs(): void
    {
        $archivoId = $this->subirAdjunto($this->conPqrs, ['tipo' => 'pqrs', 'pqrs_id' => $this->pqrsId])
            ->assertOk()->json('data.0.id');

        $this->getJson("/api/ventanilla/radica-recibida/{$this->conPqrs}/archivos/adjuntos/listar")->assertOk();
        $this->deleteJson("/api/ventanilla/radica-recibida/{$this->conPqrs}/archivos/adjuntos/{$archivoId}/eliminar")
            ->assertOk();
    }

    public function test_con_permiso_pqrsf_no_toca_un_recibido_sin_pqrs(): void
    {
        $this->subirAdjunto($this->sinPqrs)->assertForbidden();
        $this->getJson("/api/ventanilla/radica-recibida/{$this->sinPqrs}/archivos/adjuntos/listar")->assertForbidden();
    }

    public function test_sin_el_permiso_de_la_accion_responde_403(): void
    {
        $this->actingAs($this->user)
            ->deleteJson("/api/ventanilla/radica-recibida/{$this->conPqrs}/archivos/digital/delete")->assertForbidden();
    }
}
