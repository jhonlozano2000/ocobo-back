<?php

namespace Tests\Feature\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosArchivos;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoArchivos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M03-11/12: borrar el digital o un adjunto de un radicado interno no dejaba historial y borraba
 * el binario antes de tocar la BD. En los seis endpoints el archivo se borra ahora al confirmar.
 */
class BorradoArchivosIndividualTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        (new \ReflectionProperty(ArchivoHelper::class, 'storageCache'))->setValue(null, []);
        Storage::fake('radicados_internos');
        Storage::fake('radicados_enviados');

        // La BD de pruebas puede no tener aplicados los renombres de tablas; TEMPORARY las suple
        foreach (['responsa', 'destina', 'proyectores', 'firmantes', 'archivos'] as $sufijo) {
            if (! Schema::hasTable("ventanilla_radica_internos_{$sufijo}")) {
                DB::statement("CREATE TEMPORARY TABLE ventanilla_radica_internos_{$sufijo} LIKE ventanilla_radica_interno_{$sufijo}");
            }
        }
        if (! Schema::hasTable('ventanilla_radica_internos_archivos_eliminados')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_internos_archivos_eliminados (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, radica_interno_id BIGINT UNSIGNED, archivo VARCHAR(255),
                eliminado_por BIGINT UNSIGNED NULL, deleted_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        }
        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_enviados_responsable LIKE ventanilla_radica_enviados_responsa');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Interna' => ['Mostrar', 'Eliminar digital', 'Eliminar adjuntos'], 'Enviada' => ['Mostrar', 'Eliminar adjuntos']] as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $this->user->givePermissionTo(Permission::findOrCreate("Radicar -> Cores. {$modulo} -> {$accion}", 'web'));
            }
        }
    }

    private function insertar(string $tabla, array $fila): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table($tabla)->insertGetId($fila);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function interno(?string $digital = null): int
    {
        return $this->insertar('ventanilla_radica_internos', [
            'num_radicado' => 'M0311-'.uniqid(), 'clasifica_documen_id' => 1,
            'usuario_crea' => $this->user->id, 'archivo_digital' => $digital,
        ]);
    }

    public function test_eliminar_adjunto_interno_queda_en_historial_y_borra_el_archivo(): void
    {
        $id = $this->interno();
        $adjunto = $this->insertar('ventanilla_radica_internos_archivos', ['radicado_id' => $id, 'archivo' => "adj-int-$id.pdf"]);
        Storage::disk('radicados_internos')->put("adj-int-$id.pdf", 'pdf');

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/radica-interno/{$id}/archivos/adjuntos/{$adjunto}/eliminar")->assertOk();

        $this->assertDatabaseMissing('ventanilla_radica_internos_archivos', ['id' => $adjunto]);
        $this->assertDatabaseHas('ventanilla_radica_internos_archivos_eliminados', [
            'radica_interno_id' => $id, 'archivo' => "adj-int-$id.pdf", 'eliminado_por' => $this->user->id,
        ]);
        Storage::disk('radicados_internos')->assertMissing("adj-int-$id.pdf");
    }

    public function test_eliminar_digital_interno_queda_en_historial_y_borra_el_archivo(): void
    {
        $id = $this->interno("dig-int-{$this->user->id}.pdf");
        Storage::disk('radicados_internos')->put("dig-int-{$this->user->id}.pdf", 'pdf');

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/radica-interno/{$id}/archivos/digital/delete")->assertOk();

        $this->assertDatabaseHas('ventanilla_radica_internos', ['id' => $id, 'archivo_digital' => null]);
        $this->assertDatabaseHas('ventanilla_radica_internos_archivos_eliminados', [
            'radica_interno_id' => $id, 'archivo' => "dig-int-{$this->user->id}.pdf",
        ]);
        Storage::disk('radicados_internos')->assertMissing("dig-int-{$this->user->id}.pdf");
    }

    public function test_si_falla_el_borrado_del_adjunto_interno_el_archivo_se_conserva(): void
    {
        $id = $this->interno();
        $adjunto = $this->insertar('ventanilla_radica_internos_archivos', ['radicado_id' => $id, 'archivo' => "adj-int-$id.pdf"]);
        Storage::disk('radicados_internos')->put("adj-int-$id.pdf", 'pdf');
        VentanillaRadicaInternoArchivos::deleting(fn () => throw new \RuntimeException('fallo simulado'));

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/radica-interno/{$id}/archivos/adjuntos/{$adjunto}/eliminar")->assertStatus(500);

        $this->assertDatabaseHas('ventanilla_radica_internos_archivos', ['id' => $adjunto]);
        $this->assertDatabaseMissing('ventanilla_radica_internos_archivos_eliminados', ['radica_interno_id' => $id]);
        Storage::disk('radicados_internos')->assertExists("adj-int-$id.pdf");
    }

    public function test_si_falla_el_borrado_del_adjunto_enviado_el_archivo_se_conserva(): void
    {
        $id = $this->insertar('ventanilla_radica_enviados', [
            'num_radicado' => 'M0311E-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        $adjunto = $this->insertar('ventanilla_radica_enviados_archivos', ['radica_enviado_id' => $id, 'archivo' => "adj-env-$id.pdf"]);
        Storage::disk('radicados_enviados')->put("adj-env-$id.pdf", 'pdf');
        VentanillaRadicaEnviadosArchivos::deleting(fn () => throw new \RuntimeException('fallo simulado'));

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/radica-enviada/{$id}/archivos/adjuntos/{$adjunto}/eliminar")->assertStatus(500);

        $this->assertDatabaseHas('ventanilla_radica_enviados_archivos', ['id' => $adjunto]);
        Storage::disk('radicados_enviados')->assertExists("adj-env-$id.pdf");
    }
}
