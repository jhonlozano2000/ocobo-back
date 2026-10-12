<?php

namespace Tests\Feature\ControlAcceso;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M17-05: cargosDisponibles no debe ejecutar N+1 queries al listar y jerarquizar cargos.
 */
class CargosDisponiblesRendimientoTest extends TestCase
{
    use DatabaseTransactions;

    private const PERMISO = 'Control de acceso - Cargos -> Listar';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate(self::PERMISO, 'web');
        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(self::PERMISO);
    }

    public function test_sin_permiso_responde_403(): void
    {
        $otro = User::factory()->create(['estado' => 1]);
        $this->actingAs($otro)
            ->getJson('/api/control-acceso/user-cargos/cargos-disponibles')
            ->assertForbidden();
    }

    public function test_cargos_disponibles_retorna_estructura_con_consultas_acotadas(): void
    {
        // Crear jerarquía de 3 niveles y 2 cargos asignables
        $dep = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Gerencia General',
            'cod_organico' => 'GG-TEST',
            'parent' => null,
            'estado' => 1,
        ]);

        $ofi = CalidadOrganigrama::create([
            'tipo' => 'Oficina',
            'nom_organico' => 'Oficina de Pruebas',
            'cod_organico' => 'OP-TEST',
            'parent' => $dep->id,
            'estado' => 1,
        ]);

        $cargo1 = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Líder de Pruebas',
            'cod_organico' => 'LP-TEST',
            'parent' => $ofi->id,
            'estado' => 1,
        ]);

        $cargo2 = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Analista de Pruebas',
            'cod_organico' => 'AP-TEST',
            'parent' => $ofi->id,
            'estado' => 1,
        ]);

        // Asignar un usuario activo al cargo 1
        UserCargo::create([
            'user_id' => $this->user->id,
            'cargo_id' => $cargo1->id,
            'fecha_inicio' => now()->subMonth(),
            'estado' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->user)
            ->getJson('/api/control-acceso/user-cargos/cargos-disponibles?incluir_ocupados=true')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'cargos',
                    'resumen' => [
                        'total_cargos',
                        'cargos_disponibles',
                        'cargos_ocupados',
                        'filtro_aplicado',
                    ],
                ],
            ]);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Máximo 10 queries (auth + permisos + query con withCount + carga de organigrama)
        $this->assertLessThanOrEqual(10, $queries, "Se ejecutaron {$queries} queries, posible N+1 detectado.");

        // Validar que cargo2 aparece disponible y cargo1 ocupado
        $cargos = collect($response->json('data.cargos'));
        $c1 = $cargos->firstWhere('id', $cargo1->id);
        $c2 = $cargos->firstWhere('id', $cargo2->id);

        $this->assertNotNull($c1);
        $this->assertNotNull($c2);
        $this->assertFalse($c1['disponible']);
        $this->assertTrue($c2['disponible']);
        $this->assertCount(3, $c1['jerarquia']); // Dependencia -> Oficina -> Cargo
    }
}
