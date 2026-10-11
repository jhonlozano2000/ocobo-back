<?php

namespace Tests\Feature\VentanillaUnica;

use App\Http\Middleware\AccesoRadicado;
use App\Models\User;
use App\Services\VentanillaUnica\PqrsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M04-03: la identidad del afectado en denuncias y PQRS anónimas solo la ve quien tiene
 * el permiso 'Radicar -> PQRSF -> Ver identidad reservada'.
 */
class ReservaIdentidadPqrsTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Radicar -> PQRSF -> ';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // BD de pruebas sin la migración de es_anonimo; la tabla TEMPORARY (sin commit implícito) la suple
        if (! Schema::hasColumn('ventanilla_pqrs', 'es_anonimo')) {
            // Mismo DDL de la tabla real, sin llaves foráneas y con la columna nueva (ALTER sí haría commit)
            $ddl = DB::selectOne('SHOW CREATE TABLE ventanilla_pqrs')->{'Create Table'};
            $ddl = preg_replace(['/^\s*CONSTRAINT `[^`]+` FOREIGN KEY .*$\n?/m', '/,(\s*\n\))/'], ['', '$1'], $ddl);
            $ddl = preg_replace('/^CREATE TABLE `ventanilla_pqrs` \(/', 'CREATE TEMPORARY TABLE `ventanilla_pqrs` (`es_anonimo` TINYINT(1) NOT NULL DEFAULT 0,', $ddl);
            DB::statement($ddl);
        }

        // El ABAC pasa por la vista, que lee la tabla real y no la TEMPORARY; aquí solo se prueba la reserva
        $this->withoutMiddleware(AccesoRadicado::class);

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Listar', 'Mostrar', 'Editar', 'Crear'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
        }
    }

    private function pqrs(array $datos, string $tipo = 'Petición'): int
    {
        Schema::disableForeignKeyConstraints();
        $tipoId = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => $tipo]);
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0403-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        $pqrs = DB::table('ventanilla_pqrs')->insertGetId($datos + [
            'ventanilla_radica_reci_id' => $radicado, 'tipo_pqrs_id' => $tipoId, 'fecha_vencimiento' => '2026-12-31',
            'nom_afectado' => 'Ana Denunciante', 'num_docu_afectado' => '1234567', 'tel_afectado' => '555',
            'movil_afectado' => '300', 'dir_afectado' => 'Calle 1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $pqrs;
    }

    private function afectado(int $id): array
    {
        return $this->actingAs($this->user)->getJson("/api/ventanilla/pqrs/{$id}")->assertOk()->json('data.afectado');
    }

    public function test_denuncia_y_pqrs_anonima_ocultan_la_identidad(): void
    {
        $anonima = $this->pqrs(['es_anonimo' => 1]);
        $denuncia = $this->pqrs([], 'Denuncia');
        $normal = $this->pqrs([]);

        $this->assertSame(['id' => null, 'nombre' => 'Anónimo', 'documento' => null, 'direccion' => null, 'telefono' => null, 'movil' => null], $this->afectado($anonima));
        $this->assertSame('Identidad reservada', $this->afectado($denuncia)['nombre']);
        $this->assertNull($this->afectado($denuncia)['documento']);
        $this->assertSame('1234567', $this->afectado($normal)['documento']);

        $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.'Ver identidad reservada', 'web'));
        $this->assertSame('Ana Denunciante', $this->afectado($anonima)['nombre']);
        $this->assertSame('1234567', $this->afectado($denuncia)['documento']);
    }

    public function test_quitar_la_reserva_exige_el_permiso(): void
    {
        $anonima = $this->pqrs(['es_anonimo' => 1]);

        $this->actingAs($this->user)->putJson("/api/ventanilla/pqrs/{$anonima}", ['es_anonimo' => false])->assertStatus(422);
        $this->assertSame(1, (int) DB::table('ventanilla_pqrs')->where('id', $anonima)->value('es_anonimo'));

        $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.'Ver identidad reservada', 'web'));
        $this->actingAs($this->user)->putJson("/api/ventanilla/pqrs/{$anonima}", ['es_anonimo' => false])->assertOk();
        $this->assertSame(0, (int) DB::table('ventanilla_pqrs')->where('id', $anonima)->value('es_anonimo'));
    }

    public function test_pqrs_anonima_no_exige_nombre_ni_documento(): void
    {
        Schema::disableForeignKeyConstraints();
        $tipoId = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Queja']);
        Schema::enableForeignKeyConstraints();
        $payload = ['tipo_pqrs_id' => $tipoId, 'prioridad' => 'Normal', 'detalle_solicitud' => 'Hechos'];

        $this->actingAs($this->user)->postJson('/api/ventanilla/pqrs', $payload)->assertStatus(422)->assertJsonValidationErrors('nom_afectado');
        $this->actingAs($this->user)->postJson('/api/ventanilla/pqrs', $payload + ['es_anonimo' => true])
            ->assertJsonMissingValidationErrors(['nom_afectado', 'num_docu_afectado']);
    }

    public function test_la_pqrs_creada_desde_el_radicado_guarda_es_anonimo(): void
    {
        Schema::disableForeignKeyConstraints();
        $tipoId = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Queja']);
        $tercero = DB::table('gestion_terceros')->insertGetId(['num_docu_nit' => '987', 'nom_razo_soci' => 'Pedro']);
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0403-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id, 'tercero_id' => $tercero,
        ]);
        Schema::enableForeignKeyConstraints();

        $pqrs = app(PqrsService::class)->crearDesdeRadicado($radicado, ['tipo_pqrs_id' => $tipoId, 'es_anonimo' => true]);

        $this->assertTrue($pqrs->fresh()->es_anonimo);
        $this->assertTrue($pqrs->ocultaIdentidadPara($this->user));
    }
}
