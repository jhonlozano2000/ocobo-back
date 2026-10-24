<?php

namespace Tests\Feature\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-06/M02-06/M03-06: al eliminar un radicado los archivos se borraban antes del commit; si la
 * transacción fallaba, el registro quedaba en BD sin su documento. Ahora se borran al confirmar.
 */
class BorradoArchivosRadicadoTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // ArchivoHelper cachea los discos: se limpia para que use los falsos
        (new \ReflectionProperty(ArchivoHelper::class, 'storageCache'))->setValue(null, []);
        Storage::fake('radicados_recibidos');
        Storage::fake('radicados_enviados');

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Radicar -> Cores. Recibida -> Eliminar', 'Radicar -> Cores. Recibida -> Ver Todos', 'Radicar -> Cores. Enviada -> Eliminar'] as $permiso) {
            $this->user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
    }

    /** Radicado recibido con documento principal y un adjunto en disco. */
    private function recibido(): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0106-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
            'archivo_digital' => "principal-{$this->user->id}-".uniqid().'.pdf', 'created_at' => now(),
        ]);
        DB::table('ventanilla_radica_reci_archivos')->insert(['radicado_id' => $id, 'archivo' => "adjunto-$id.pdf"]);
        Schema::enableForeignKeyConstraints();

        Storage::disk('radicados_recibidos')->put(DB::table('ventanilla_radica_reci')->where('id', $id)->value('archivo_digital'), 'pdf');
        Storage::disk('radicados_recibidos')->put("adjunto-$id.pdf", 'pdf');

        return $id;
    }

    private function archivosDe(int $id): array
    {
        return [DB::table('ventanilla_radica_reci')->where('id', $id)->value('archivo_digital'), "adjunto-$id.pdf"];
    }

    private function fallarAlBorrar(int $id): void
    {
        VentanillaRadicaReci::deleting(function ($radicado) use ($id) {
            if ($radicado->id === $id) {
                throw new \RuntimeException('fallo simulado');
            }
        });
    }

    public function test_si_el_borrado_falla_los_archivos_se_conservan(): void
    {
        $id = $this->recibido();
        $archivos = $this->archivosDe($id);
        $this->fallarAlBorrar($id);

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/radica-recibida/{$id}")->assertStatus(500);

        $this->assertDatabaseHas('ventanilla_radica_reci', ['id' => $id]);
        foreach ($archivos as $archivo) {
            Storage::disk('radicados_recibidos')->assertExists($archivo);
        }
    }

    public function test_borrado_confirmado_elimina_los_archivos(): void
    {
        $id = $this->recibido();
        $archivos = $this->archivosDe($id);

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/radica-recibida/{$id}")->assertOk();

        $this->assertSoftDeleted('ventanilla_radica_reci', ['id' => $id]);
        foreach ($archivos as $archivo) {
            Storage::disk('radicados_recibidos')->assertMissing($archivo);
        }
    }

    public function test_no_hay_borrado_en_lote_de_radicados(): void
    {
        // Los radicados se borran de a uno (el último) o se anulan, para no dejar huecos en el consecutivo
        $id = $this->recibido();

        $this->actingAs($this->user)->deleteJson('/api/ventanilla/radica-recibida', ['ids' => [$id]])->assertStatus(405);
        $this->actingAs($this->user)->deleteJson('/api/ventanilla/radica-enviada', ['ids' => [$id]])->assertStatus(405);

        $this->assertDatabaseHas('ventanilla_radica_reci', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_borrar_enviado_elimina_tambien_los_adjuntos_del_disco(): void
    {
        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_enviados_responsable LIKE ventanilla_radica_enviados_responsa');
        }
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'M0206-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->user->id,
            'archivo_digital' => "enviado-{$this->user->id}.pdf",
        ]);
        DB::table('ventanilla_radica_enviados_archivos')->insert(['radica_enviado_id' => $id, 'archivo' => "adjunto-env-$id.pdf"]);
        Schema::enableForeignKeyConstraints();
        Storage::disk('radicados_enviados')->put("enviado-{$this->user->id}.pdf", 'pdf');
        Storage::disk('radicados_enviados')->put("adjunto-env-$id.pdf", 'pdf');

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/radica-enviada/{$id}")->assertOk();

        Storage::disk('radicados_enviados')->assertMissing("enviado-{$this->user->id}.pdf");
        Storage::disk('radicados_enviados')->assertMissing("adjunto-env-$id.pdf");
    }
}
