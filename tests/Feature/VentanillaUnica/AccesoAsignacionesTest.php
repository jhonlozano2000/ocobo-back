<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-08, M03-15..18: las rutas de responsables, firmantes, proyectores y destinatarios por id
 * solo exigían el permiso Editar del módulo; cualquier usuario listaba, veía, movía o borraba
 * asignaciones de radicados que no ve.
 */
class AccesoAsignacionesTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        // La BD de pruebas puede no tener aplicados los renombres de tablas; TEMPORARY las suple
        foreach (['responsa', 'destina', 'proyectores', 'firmantes'] as $sufijo) {
            if (! Schema::hasTable("ventanilla_radica_internos_{$sufijo}")) {
                DB::statement("CREATE TEMPORARY TABLE ventanilla_radica_internos_{$sufijo} LIKE ventanilla_radica_interno_{$sufijo}");
            }
        }
        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_enviados_responsable LIKE ventanilla_radica_enviados_responsa');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        foreach (['Recibida', 'Interna', 'Enviada'] as $modulo) {
            foreach (['Mostrar', 'Editar'] as $accion) {
                $this->user->givePermissionTo(Permission::findOrCreate("Radicar -> Cores. {$modulo} -> {$accion}", 'web'));
            }
        }
    }

    /** Inserta sin FKs (la BD de pruebas no tiene catálogos) y devuelve el id. */
    private function insertar(string $tabla, array $fila): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table($tabla)->insertGetId($fila);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function recibido(int $usuarioCrea): int
    {
        return $this->insertar('ventanilla_radica_reci', [
            'num_radicado' => 'M0108-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $usuarioCrea, 'created_at' => now(),
        ]);
    }

    private function enviado(int $usuarioCrea): int
    {
        return $this->insertar('ventanilla_radica_enviados', [
            'num_radicado' => 'M0108E-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $usuarioCrea,
        ]);
    }

    public function test_responsable_de_radicado_ajeno_responde_404(): void
    {
        $ajeno = $this->insertar('ventanilla_radica_reci_responsa', ['radica_reci_id' => $this->recibido($this->otro->id), 'users_cargos_id' => 1, 'custodio' => 0]);
        $propio = $this->insertar('ventanilla_radica_reci_responsa', ['radica_reci_id' => $this->recibido($this->user->id), 'users_cargos_id' => 1, 'custodio' => 0]);

        $this->actingAs($this->user)->getJson("/api/ventanilla/responsables/{$ajeno}")->assertNotFound();
        $this->actingAs($this->user)->deleteJson("/api/ventanilla/responsables/{$ajeno}")->assertNotFound();
        $this->assertDatabaseHas('ventanilla_radica_reci_responsa', ['id' => $ajeno]);

        $this->actingAs($this->user)->getJson("/api/ventanilla/responsables/{$propio}")->assertOk();
    }

    public function test_firmante_interno_ajeno_no_se_elimina(): void
    {
        $interno = $this->insertar('ventanilla_radica_internos', [
            'num_radicado' => 'M0315-'.uniqid(), 'clasifica_documen_id' => 1, 'usuario_crea' => $this->otro->id,
        ]);
        $firmante = $this->insertar('ventanilla_radica_internos_firmantes', ['radica_interno_id' => $interno, 'users_id' => $this->otro->id]);

        $this->actingAs($this->user)->deleteJson("/api/ventanilla/firmantes-internos/{$firmante}")->assertNotFound();
        $this->assertDatabaseHas('ventanilla_radica_internos_firmantes', ['id' => $firmante]);
    }

    public function test_no_se_puede_mover_una_asignacion_a_un_radicado_ajeno(): void
    {
        $responsable = $this->insertar('ventanilla_radica_enviados_responsable', ['radica_enviado_id' => $this->enviado($this->user->id), 'users_cargos_id' => 1, 'custodio' => 0]);
        $ajeno = $this->enviado($this->otro->id);

        $this->actingAs($this->user)
            ->putJson("/api/ventanilla/responsables-enviados/{$responsable}", ['radica_enviado_id' => $ajeno])
            ->assertNotFound();
        $this->assertDatabaseMissing('ventanilla_radica_enviados_responsable', ['id' => $responsable, 'radica_enviado_id' => $ajeno]);
    }

    public function test_listado_y_alta_globales_ya_no_existen(): void
    {
        foreach (['responsables', 'responsables-internos', 'proyectores-internos', 'firmantes-internos', 'destinatarios-internos',
            'responsables-enviados', 'firmantes-enviados', 'proyectores-enviados'] as $recurso) {
            $this->actingAs($this->user)->getJson("/api/ventanilla/{$recurso}")->assertNotFound();
            $this->actingAs($this->user)->postJson("/api/ventanilla/{$recurso}", [])->assertNotFound();
        }
    }
}
