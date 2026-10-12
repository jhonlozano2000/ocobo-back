<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sin dependencia_origen_id guardada, la dependencia de origen es la del cargo del custodio
 * principal y, sin custodio, la del creador; también con la relación precargada (Mi bandeja).
 */
class RadicadoInternoDependenciaOrigenTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ventanilla_radica_internos_responsa')) {
            $this->markTestSkipped('BD de pruebas sin la tabla ventanilla_radica_internos_responsa.');
        }
    }

    /**
     * Crea Dependencia > Oficina > Cargo y asigna el cargo a un usuario nuevo.
     *
     * @return array{0: CalidadOrganigrama, 1: User, 2: int} dependencia, usuario, users_cargos.id
     */
    private function usuarioEnDependencia(string $sufijo): array
    {
        $dependencia = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => "Dep {$sufijo}", 'cod_organico' => "D{$sufijo}"]);
        $oficina = CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => "Ofi {$sufijo}", 'cod_organico' => "O{$sufijo}", 'parent' => $dependencia->id]);
        $cargo = CalidadOrganigrama::create(['tipo' => 'Cargo', 'nom_organico' => "Cargo {$sufijo}", 'cod_organico' => "C{$sufijo}", 'parent' => $oficina->id]);

        $user = User::factory()->create(['estado' => 1]);
        $userCargoId = DB::table('users_cargos')->insertGetId([
            'user_id' => $user->id,
            'cargo_id' => $cargo->id,
            'fecha_inicio' => now()->toDateString(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$dependencia, $user, $userCargoId];
    }

    private function interno(string $num, int $creadorId, ?int $dependenciaOrigenId = null): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => $num,
            'clasifica_documen_id' => 999999,
            'asunto' => 'Prueba dependencia origen',
            'usuario_crea' => $creadorId,
            'dependencia_origen_id' => $dependenciaOrigenId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function responsable(int $internoId, int $userCargoId, bool $custodio): void
    {
        DB::table('ventanilla_radica_internos_responsa')->insert([
            'radica_interno_id' => $internoId,
            'users_cargos_id' => $userCargoId,
            'custodio' => $custodio,
            'created_at' => now(),
        ]);
    }

    private function assertDependenciaOrigen(array $esperado, int $id): void
    {
        $this->assertSame($esperado, VentanillaRadicaInterno::find($id)->dependencia_origen);
        $this->assertSame(
            $esperado,
            VentanillaRadicaInterno::with(['dependenciaOrigen', 'responsables.userCargo.cargo'])->find($id)->dependencia_origen
        );
    }

    public function test_dependencia_origen_es_la_del_custodio_principal(): void
    {
        [, $creador] = $this->usuarioEnDependencia('Creador');
        [, , $otroCargo] = $this->usuarioEnDependencia('Otro');
        [$depCustodio, , $custodioCargo] = $this->usuarioEnDependencia('Custodio');

        $id = $this->interno('DOT-TEST-0001', $creador->id);
        $this->responsable($id, $otroCargo, false);
        $this->responsable($id, $custodioCargo, true);

        $this->assertDependenciaOrigen(['id' => $depCustodio->id, 'nombre' => 'Dep Custodio'], $id);
    }

    public function test_listado_de_ventanilla_devuelve_la_dependencia_del_custodio(): void
    {
        [, $creador] = $this->usuarioEnDependencia('Creador');
        [$depCustodio, , $custodioCargo] = $this->usuarioEnDependencia('Custodio');
        $creador->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Interna -> Listar', 'web'));

        $id = $this->interno('DOT-TEST-0004', $creador->id);
        $this->responsable($id, $custodioCargo, true);

        // El listado reemplaza `responsables` por arreglos antes de serializar
        $fila = collect($this->actingAs($creador)->getJson('/api/ventanilla/radica-interno?per_page=100')->assertOk()->json('data'))
            ->firstWhere('id', $id);

        $this->assertSame(['id' => $depCustodio->id, 'nombre' => 'Dep Custodio'], $fila['dependencia_origen']);
    }

    public function test_sin_custodio_se_usa_la_dependencia_del_creador(): void
    {
        [$depCreador, $creador] = $this->usuarioEnDependencia('Creador');

        $id = $this->interno('DOT-TEST-0003', $creador->id);

        $this->assertDependenciaOrigen(['id' => $depCreador->id, 'nombre' => 'Dep Creador'], $id);
    }

    public function test_dependencia_origen_guardada_tiene_prioridad(): void
    {
        $guardada = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'Dep Guardada', 'cod_organico' => 'DGT']);
        [, , $custodioCargo] = $this->usuarioEnDependencia('Custodio');

        $id = $this->interno('DOT-TEST-0002', User::factory()->create()->id, $guardada->id);
        $this->responsable($id, $custodioCargo, true);

        $this->assertDependenciaOrigen(['id' => $guardada->id, 'nombre' => 'Dep Guardada'], $id);
    }
}
