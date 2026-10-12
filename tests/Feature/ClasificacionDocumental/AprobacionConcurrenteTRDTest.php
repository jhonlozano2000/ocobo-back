<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M10-09: la aprobación solo bloqueaba la versión aprobada; dos versiones TEMP de la misma
 * dependencia aprobadas a la vez podían quedar ambas ACTIVO.
 */
class AprobacionConcurrenteTRDTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $dep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->dep = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep M1009', 'cod_organico' => 'M19'.random_int(100, 999)]);
        foreach (['TRD -> Crear', 'TRD -> Versiones', 'Jefe de Archivo'] as $permiso) {
            $this->user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
    }

    private function versionTemp(): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('clasificacion_documental_trd_versiones')->insertGetId([
            'dependencia_id' => $this->dep, 'version' => random_int(1000, 9999), 'estado_version' => 'TEMP',
            'user_register' => User::factory()->create()->id,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    public function test_la_aprobacion_bloquea_todas_las_versiones_de_la_dependencia(): void
    {
        $primera = $this->versionTemp();
        $segunda = $this->versionTemp();

        $bloqueos = [];
        DB::listen(function (QueryExecuted $q) use (&$bloqueos) {
            if (str_contains($q->sql, 'for update') && str_contains($q->sql, '`dependencia_id`')) {
                $bloqueos[] = $q->sql;
            }
        });

        foreach ([$primera, $segunda] as $version) {
            $this->actingAs($this->user)->postJson('/api/clasifica-documental/trd-versiones/aprobar/'.$this->dep, ['versionId' => $version, 'observaciones' => 'ok'])
                ->assertOk();
        }

        $this->assertCount(2, $bloqueos);
        $this->assertSame(1, DB::table('clasificacion_documental_trd_versiones')->where('dependencia_id', $this->dep)->where('estado_version', 'ACTIVO')->count());
    }

    public function test_crear_version_con_otra_pendiente_no_deja_la_transaccion_abierta(): void
    {
        $this->versionTemp();
        $nivel = DB::transactionLevel();

        $this->actingAs($this->user)->postJson('/api/clasifica-documental/trd-versiones', ['dependencia_id' => $this->dep])
            ->assertStatus(400);

        $this->assertSame($nivel, DB::transactionLevel());
    }
}
