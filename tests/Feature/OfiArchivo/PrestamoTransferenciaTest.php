<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests de lifecycle de Préstamos y Transferencias del módulo Expedientes.
 *
 * Cubre: CRUD préstamos, devolución, estadísticas, transferencias
 * (aprobación/rechazo/inmutabilidad), eliminaciones.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-26
 */
class PrestamoTransferenciaTest extends TestCase
{

    protected User $user;
    protected OfiArchivoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Gestion de Archivo -> Expedientes -> Ver',
            'Gestion de Archivo -> Expedientes -> Crear',
            'Gestion de Archivo -> Prestamos -> Ver',
            'Gestion de Archivo -> Prestamos -> Crear',
            'Gestion de Archivo -> Prestamos -> Devolver',
            'Gestion de Archivo -> Transferencias -> Ver',
            'Gestion de Archivo -> Transferencias -> Crear',
            'Gestion de Archivo -> Transferencias -> Aprobar',
            'Gestion de Archivo -> Eliminaciones -> Ver',
            'Gestion de Archivo -> Eliminaciones -> Crear',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Archivo Prestamo Test']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);

        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dep Prestamo',
            'cod_organico' => 'DEPP01',
        ]);

        $trd = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-PRES-01',
            'nom' => 'Serie Prestamo',
            'user_register' => $this->user->id,
        ]);

        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => 'EXP-PRES-'.uniqid(),
            'nombre_expediente' => 'Expediente Préstamo Test',
            'anio' => (int) now()->year,
            'dependencia_id' => $dependencia->id,
            'serie_trd_id' => $trd->id,
            'estado' => 'abierto',
            'fecha_apertura' => now()->toDateString(),
            'usuario_apertura_id' => $this->user->id,
        ]);
    }

    // ==================== PRÉSTAMOS ====================

    /** @test */
    public function puede_crear_prestamo()
    {
        $solicitante = User::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/archivo/prestamos', [
                'expediente_id' => $this->expediente->id,
                'solicitante_id' => $solicitante->id,
                'fecha_devolucion_esperada' => now()->addDays(15)->toDateString(),
                'observaciones' => 'Préstamo de prueba',
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'estado'],
            ]);

        $this->assertDatabaseHas('ofi_archivo_prestamos', [
            'expediente_id' => $this->expediente->id,
            'estado' => 'prestado',
        ]);
    }

    /** @test */
    public function no_puede_prestar_expediente_cerrado()
    {
        $this->expediente->update(['estado' => 'cerrado']);
        $solicitante = User::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/archivo/prestamos', [
                'expediente_id' => $this->expediente->id,
                'solicitante_id' => $solicitante->id,
                'fecha_devolucion_esperada' => now()->addDays(15)->toDateString(),
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function puede_devolver_prestamo()
    {
        $prestamo = $this->crearPrestamo();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/prestamos/{$prestamo->id}/devolver")
            ->assertStatus(200);

        $prestamo->refresh();
        $this->assertEquals('devuelto', $prestamo->estado);
        $this->assertNotNull($prestamo->fecha_devolucion_real);
    }

    /** @test */
    public function no_puede_devolver_prestamo_ya_devuelto()
    {
        $prestamo = $this->crearPrestamo();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/prestamos/{$prestamo->id}/devolver");

        $this->actingAs($this->user)
            ->postJson("/api/archivo/prestamos/{$prestamo->id}/devolver")
            ->assertStatus(422);
    }

    /** @test */
    public function estadisticas_prestamos()
    {
        $this->crearPrestamo();
        $this->crearPrestamo();

        $response = $this->actingAs($this->user)
            ->getJson('/api/archivo/prestamos-estadisticas')
            ->assertStatus(200);

        $this->assertGreaterThanOrEqual(2, $response->json('data.activos'));
    }

    /** @test */
    public function sin_permiso_no_puede_crear_prestamo()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/archivo/prestamos', [])
            ->assertStatus(403);
    }

    // ==================== TRANSFERENCIAS ====================

    /** @test */
    public function puede_crear_transferencia()
    {
        $responsable = User::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/archivo/transferencias', [
                'expediente_id' => $this->expediente->id,
                'tipo' => 'primaria',
                'origen' => 'Archivo Central',
                'destino' => 'Archivo Regional',
                'responsable_origen_id' => $this->user->id,
                'responsable_destino_id' => $responsable->id,
                'fecha_transferencia' => now()->toDateString(),
                'observaciones' => 'Transferencia de prueba',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('ofi_archivo_transferencias', [
            'expediente_id' => $this->expediente->id,
            'estado' => 'pendiente',
        ]);
    }

    /** @test */
    public function puede_aprobar_transferencia()
    {
        $t = $this->crearTransferencia();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'estado' => 'aprobada',
            ])
            ->assertStatus(200);

        $t->refresh();
        $this->assertEquals('aprobada', $t->estado);

        $this->expediente->refresh();
        $this->assertEquals('transferido', $this->expediente->estado);
    }

    /** @test */
    public function puede_rechazar_transferencia_con_observaciones()
    {
        $t = $this->crearTransferencia();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'estado' => 'rechazada',
                'observaciones' => 'No cumple requisitos',
            ])
            ->assertStatus(200);

        $t->refresh();
        $this->assertEquals('rechazada', $t->estado);

        // El expediente NO cambia de estado
        $this->expediente->refresh();
        $this->assertEquals('abierto', $this->expediente->estado);
    }

    /** @test */
    public function rechazo_sin_observaciones_falla()
    {
        $t = $this->crearTransferencia();

        $this->actingAs($this->user)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'estado' => 'rechazada',
                'observaciones' => '',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function transferencia_decidida_es_inmutable()
    {
        $t = $this->crearTransferencia();

        // Aprobar
        $this->actingAs($this->user)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'estado' => 'aprobada',
            ]);

        // Intentar rechazar
        $this->actingAs($this->user)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'estado' => 'rechazada',
                'observaciones' => 'Cambio de opinión',
            ])
            ->assertStatus(422);

        $t->refresh();
        $this->assertEquals('aprobada', $t->estado);
    }

    // ==================== ELIMINACIONES ====================

    /** @test */
    public function puede_registrar_eliminacion()
    {
        $this->expediente->update(['estado' => 'cerrado']);
        $aprobador = User::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/archivo/eliminaciones', [
                'expediente_id' => $this->expediente->id,
                'metodo' => 'destruccion_fisica',
                'responsable_ids' => [$this->user->id, $aprobador->id],
                'testigos' => 'Juan Pérez, María García',
                'aprobado_por_id' => $aprobador->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('ofi_archivo_eliminaciones', [
            'expediente_id' => $this->expediente->id,
            'metodo' => 'destruccion_fisica',
        ]);
    }

    /** @test */
    public function no_puede_eliminar_expediente_abierto()
    {
        $aprobador = User::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/archivo/eliminaciones', [
                'expediente_id' => $this->expediente->id,
                'metodo' => 'borrado_seguro',
                'responsable_ids' => [$this->user->id, $aprobador->id],
                'aprobado_por_id' => $aprobador->id,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function puede_listar_transferencias()
    {
        $this->crearTransferencia();
        $this->crearTransferencia();

        $this->actingAs($this->user)
            ->getJson('/api/archivo/transferencias')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }

    /** @test */
    public function puede_listar_eliminaciones()
    {
        $this->expediente->update(['estado' => 'cerrado']);
        $aprobador = User::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/archivo/eliminaciones', [
                'expediente_id' => $this->expediente->id,
                'metodo' => 'destruccion_fisica',
                'responsable_ids' => [$this->user->id, $aprobador->id],
                'aprobado_por_id' => $aprobador->id,
            ]);

        $this->actingAs($this->user)
            ->getJson('/api/archivo/eliminaciones')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    /**
     * Helper: crear un préstamo con valores por defecto.
     */
    protected function crearPrestamo(): OfiArchivoPrestamo
    {
        return OfiArchivoPrestamo::create([
            'expediente_id' => $this->expediente->id,
            'solicitante_id' => $this->user->id,
            'fecha_prestamo' => now(),
            'fecha_devolucion_esperada' => now()->addDays(10),
            'estado' => 'prestado',
            'usuario_registro_id' => $this->user->id,
        ]);
    }

    /**
     * Helper: crear una transferencia con valores por defecto.
     */
    protected function crearTransferencia(): OfiArchivoTransferencia
    {
        return OfiArchivoTransferencia::create([
            'expediente_id' => $this->expediente->id,
            'tipo' => 'primaria',
            'origen' => 'Archivo Central',
            'destino' => 'Archivo Regional',
            'responsable_origen_id' => $this->user->id,
            'responsable_destino_id' => $this->user->id,
            'fecha_transferencia' => now(),
            'estado' => 'pendiente',
            'usuario_registro_id' => $this->user->id,
        ]);
    }
}
