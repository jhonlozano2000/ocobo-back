<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M10-01: una TRD aprobada se podía editar o eliminar sin nueva versión.
 * M10-02: quien elaboraba la versión podía aprobarla.
 */
class InmutabilidadTRDTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $dep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->dep = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep M10', 'cod_organico' => 'M10'.random_int(100, 999)]);
        foreach (['TRD -> Crear', 'TRD -> Editar', 'TRD -> Eliminar', 'TRD -> Versiones', 'Jefe de Archivo'] as $permiso) {
            $this->user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
    }

    private function version(string $estado, int $creador): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('clasificacion_documental_trd_versiones')->insertGetId([
            'dependencia_id' => $this->dep, 'version' => random_int(1000, 9999), 'estado_version' => $estado, 'user_register' => $creador,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function serie(?int $versionId): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'I'.random_int(1000, 9999), 'nom' => 'Serie', 'user_register' => $this->user->id,
            'dependencia_id' => $this->dep, 'version_id' => $versionId, 'estado_version' => 'TEMP',
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    public function test_trd_aprobada_no_se_edita_elimina_ni_recibe_hijos(): void
    {
        $otro = User::factory()->create()->id;

        foreach (['ACTIVO', 'HISTORICO'] as $estado) {
            $serie = $this->serie($this->version($estado, $otro));

            $this->actingAs($this->user)->putJson("/api/clasifica-documental/trd/{$serie}", ['nom' => 'Cambiado'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'nueva versión'));
            $this->actingAs($this->user)->deleteJson("/api/clasifica-documental/trd/{$serie}")->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'nueva versión'));
            $this->actingAs($this->user)->postJson('/api/clasifica-documental/trd', [
                'tipo' => 'SubSerie', 'cod' => 'S'.random_int(1000, 9999), 'nom' => 'Hija', 'dependencia_id' => $this->dep, 'parent' => $serie,
            ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'nueva versión'));

            $this->assertSame('Serie', DB::table('clasificacion_documental_trd')->where('id', $serie)->value('nom'));
            $this->assertSame(0, DB::table('clasificacion_documental_trd')->where('parent', $serie)->count());
        }

        // En una versión TEMP sí se edita
        $borrador = $this->serie($this->version('TEMP', $otro));
        $this->actingAs($this->user)->putJson("/api/clasifica-documental/trd/{$borrador}", ['nom' => 'Cambiado'])->assertOk();
    }

    public function test_quien_registra_la_version_no_la_aprueba(): void
    {
        $propia = $this->version('TEMP', $this->user->id);
        $this->actingAs($this->user)->postJson('/api/clasifica-documental/trd-versiones/aprobar/'.$this->dep, ['versionId' => $propia, 'observaciones' => 'ok'])
            ->assertStatus(422);
        $this->assertSame('TEMP', DB::table('clasificacion_documental_trd_versiones')->where('id', $propia)->value('estado_version'));

        $ajena = $this->version('TEMP', User::factory()->create()->id);
        $this->actingAs($this->user)->postJson('/api/clasifica-documental/trd-versiones/aprobar/'.$this->dep, ['versionId' => $ajena, 'observaciones' => 'ok'])
            ->assertOk();
        $this->assertSame('ACTIVO', DB::table('clasificacion_documental_trd_versiones')->where('id', $ajena)->value('estado_version'));
    }
}
