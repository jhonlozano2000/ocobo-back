<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M11-03: la TVD no tenía aprobación; cualquiera con permiso la editaba o eliminaba.
 */
class AprobacionTVDTest extends TestCase
{
    use DatabaseTransactions;

    private User $archivista;

    private User $jefe;

    private int $dep;

    protected function setUp(): void
    {
        parent::setUp();

        // BD de pruebas sin la migración de aprobación; la tabla TEMPORARY (sin commit implícito) la suple
        if (! Schema::hasColumn('clasificacion_documental_tvd', 'fecha_aprobacion')) {
            DB::statement('CREATE TEMPORARY TABLE clasificacion_documental_tvd (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(50) NULL, cod VARCHAR(20) NULL, nom VARCHAR(255) NULL,
                descripcion TEXT NULL, soporte VARCHAR(100) NULL, gestion INT NULL, central INT NULL, total_anios INT NULL,
                disposicion_final VARCHAR(100) NULL, procedimiento TEXT NULL, parent BIGINT UNSIGNED NULL, dependencia_id BIGINT UNSIGNED NULL,
                user_register BIGINT UNSIGNED NULL, estado TINYINT(1) NOT NULL DEFAULT 1, aprobado_por BIGINT UNSIGNED NULL,
                fecha_aprobacion TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        }

        $this->dep = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep M11', 'cod_organico' => 'M11'.random_int(100, 999)]);
        $this->archivista = User::factory()->create(['estado' => 1]);
        $this->jefe = User::factory()->create(['estado' => 1]);
        foreach (['TVD -> Crear', 'TVD -> Editar', 'TVD -> Eliminar', 'TVD -> Versiones', 'Jefe de Archivo'] as $permiso) {
            $p = Permission::findOrCreate($permiso, 'web');
            $this->archivista->givePermissionTo($p);
            $this->jefe->givePermissionTo($p);
        }
    }

    private function serie(): int
    {
        return DB::table('clasificacion_documental_tvd')->insertGetId([
            'tipo' => 'SerieDocumental', 'cod' => 'V'.random_int(1000, 9999), 'nom' => 'Serie', 'dependencia_id' => $this->dep,
            'user_register' => $this->archivista->id, 'estado' => 1,
        ]);
    }

    private function aprobar(User $user)
    {
        return $this->actingAs($user)->postJson("/api/clasifica-documental/tvd/aprobar/{$this->dep}");
    }

    public function test_quien_registra_no_aprueba_y_el_jefe_si(): void
    {
        $serie = $this->serie();

        $this->aprobar($this->archivista)->assertStatus(422);
        $this->assertNull(DB::table('clasificacion_documental_tvd')->where('id', $serie)->value('fecha_aprobacion'));

        $this->aprobar($this->jefe)->assertOk();
        $this->assertSame($this->jefe->id, (int) DB::table('clasificacion_documental_tvd')->where('id', $serie)->value('aprobado_por'));
        $this->aprobar($this->jefe)->assertStatus(422);
    }

    public function test_tvd_aprobada_no_se_edita_elimina_ni_recibe_elementos(): void
    {
        $serie = $this->serie();
        // Antes de aprobar se edita
        $this->actingAs($this->archivista)->putJson("/api/clasifica-documental/tvd/{$serie}", ['nom' => 'Editada'])->assertOk();

        $this->aprobar($this->jefe)->assertOk();

        $this->actingAs($this->archivista)->putJson("/api/clasifica-documental/tvd/{$serie}", ['nom' => 'Otra'])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'ya fue aprobada'));
        $this->actingAs($this->archivista)->deleteJson("/api/clasifica-documental/tvd/{$serie}")->assertStatus(422);
        $this->actingAs($this->archivista)->postJson('/api/clasifica-documental/tvd', [
            'tipo' => 'SubSerieDocumental', 'cod' => 'S'.random_int(1000, 9999), 'nom' => 'Hija', 'dependencia_id' => $this->dep, 'parent' => $serie,
        ])->assertStatus(422);

        $this->assertSame('Editada', DB::table('clasificacion_documental_tvd')->where('id', $serie)->value('nom'));
        $this->assertSame(1, DB::table('clasificacion_documental_tvd')->where('dependencia_id', $this->dep)->count());
    }
}
