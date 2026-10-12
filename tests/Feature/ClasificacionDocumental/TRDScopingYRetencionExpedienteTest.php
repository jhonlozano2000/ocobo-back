<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M10-06: Scoping por dependencia al editar/eliminar TRD.
 * M08-14: Retención calculada por expediente desde fecha_cierre + a_g de la TRD.
 */
class TRDScopingYRetencionExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private CalidadOrganigrama $depA;

    private CalidadOrganigrama $depB;

    private User $funcionarioDepA;

    private ClasificacionDocumentalTRD $trdDepB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['TRD -> Editar', 'TRD -> Eliminar', 'TRD -> Crear', 'TRD -> Listar'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->depA = CalidadOrganigrama::create([
            'cod_organico' => 'DEP_A',
            'nom_organico' => 'Dependencia A',
            'tipo' => 'Dependencia',
            'estado' => 1,
        ]);

        $this->depB = CalidadOrganigrama::create([
            'cod_organico' => 'DEP_B',
            'nom_organico' => 'Dependencia B',
            'tipo' => 'Dependencia',
            'estado' => 1,
        ]);

        $cargoA = CalidadOrganigrama::create([
            'nom_organico' => 'Analista A',
            'cod_organico' => 'CARGO_A',
            'tipo' => 'Cargo',
            'parent' => $this->depA->id,
            'estado' => 1,
        ]);

        $this->funcionarioDepA = User::factory()->create(['estado' => 1]);
        $this->funcionarioDepA->asignarCargo($cargoA->id);
        $this->funcionarioDepA->givePermissionTo(['TRD -> Editar', 'TRD -> Eliminar']);

        $versionB = ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $this->depB->id,
            'version' => 1,
            'estado_version' => 'ACTIVO',
            'fecha_aprobacion' => now(),
            'user_register' => $this->funcionarioDepA->id,
        ]);

        $this->trdDepB = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'SER_B',
            'nom' => 'Serie Dependencia B',
            'dependencia_id' => $this->depB->id,
            'version_id' => $versionB->id,
            'a_g' => '2',
            'a_c' => '5',
            'e' => 1,
            'user_register' => $this->funcionarioDepA->id,
            'estado' => 1,
        ]);
    }

    public function test_m10_06_usuario_no_puede_editar_trd_de_otra_dependencia(): void
    {
        $this->actingAs($this->funcionarioDepA)
            ->putJson("/api/clasifica-documental/trd/{$this->trdDepB->id}", [
                'nom' => 'Nombre Modificado Ilegalmente',
                'a_g' => 3,
                'a_c' => 5,
            ])
            ->assertForbidden();
    }

    public function test_m10_06_usuario_no_puede_eliminar_trd_de_otra_dependencia(): void
    {
        $this->actingAs($this->funcionarioDepA)
            ->deleteJson("/api/clasifica-documental/trd/{$this->trdDepB->id}")
            ->assertForbidden();
    }

    public function test_m08_14_retencion_de_expediente_calculada_desde_fecha_cierre(): void
    {
        $expediente = OfiArchivoExpediente::create([
            'numero_expediente' => 'EXP-2024-TEST',
            'nombre_expediente' => 'Expediente Prueba Retención',
            'anio' => 2024,
            'dependencia_id' => $this->depB->id,
            'serie_trd_id' => $this->trdDepB->id,
            'estado' => 'cerrado',
            'fecha_apertura' => now()->subYears(3),
            'fecha_cierre' => now()->subYears(2)->addDays(7), // Cumple 2 años en exactamente 7 días
            'usuario_apertura_id' => $this->funcionarioDepA->id,
            'usuario_cierre_id' => $this->funcionarioDepA->id,
        ]);

        $this->assertNotNull($expediente->fecha_retencion_gestion_fin);
        $this->assertSame(
            $expediente->fecha_cierre->copy()->addYears(2)->toDateString(),
            $expediente->fecha_retencion_gestion_fin->toDateString()
        );

        // Ejecutar comando de alertas para la ventana de 7 días
        Artisan::call('alertas:retencion-trd', ['--dias' => '7']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->funcionarioDepA->id,
            'type' => 'retencion_trd',
            'title' => 'Retención TRD de expediente — Vence en 7 días',
        ]);
    }
}
