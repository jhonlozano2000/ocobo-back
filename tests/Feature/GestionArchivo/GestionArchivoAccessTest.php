<?php

namespace Tests\Feature\GestionArchivo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoTransferencia;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F4 — Autorización y máquina de estados del módulo Gestión de Archivo.
 *
 * Cubre A1 (cero autorización) y A2 (máquina de estados + observaciones
 * obligatorias al rechazar) del plan pruebas-y-fixes-M10-M15.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-24
 */
class GestionArchivoAccessTest extends TestCase
{

    protected User $sinPermisos;
    protected User $jefe;
    protected \App\Models\OfiArchivo\OfiArchivoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permisos idénticos a PermisosGestionArchivoSeeder (nombres exactos)
        $permisos = [
            'Gestion de Archivo -> Expedientes -> Ver',
            'Gestion de Archivo -> Expedientes -> Crear',
            'Gestion de Archivo -> Prestamos -> Ver',
            'Gestion de Archivo -> Prestamos -> Crear',
            'Gestion de Archivo -> Transferencias -> Ver',
            'Gestion de Archivo -> Transferencias -> Crear',
            'Gestion de Archivo -> Transferencias -> Aprobar',
            'Gestion de Archivo -> Dashboard -> Ver',
            'Gestion de Archivo -> Plantillas -> Ver',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rolJefe = Role::firstOrCreate(['name' => 'Jefe de Archivo']);
        $rolJefe->givePermissionTo($permisos);

        // Usuario autenticado SIN ningún permiso del módulo
        $this->sinPermisos = User::factory()->create();

        // Jefe con permisos completos
        $this->jefe = User::factory()->create();
        $this->jefe->assignRole($rolJefe);

        // Expediente mínimo para las transferencias (FK obligatoria)
        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Archivo Central',
            'cod_organico' => 'ARCH01',
        ]);

        $trd = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-ARC-01',
            'nom' => 'Serie Archivo Test',
            'user_register' => $this->jefe->id,
        ]);

        // 'anio' no está en $fillable del modelo pero es NOT NULL en BD
        OfiArchivoExpediente::unguard();

        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => 'EXP-'.uniqid(),
            'nombre_expediente' => 'Expediente de prueba',
            'anio' => now()->year,
            'dependencia_id' => $dependencia->id,
            'serie_trd_id' => $trd->id,
            'estado' => 'Abierto',
            'fecha_apertura' => now(),
            'usuario_apertura_id' => $this->jefe->id,
        ]);

        OfiArchivoExpediente::reguard();
    }

    /** @test */
    public function sin_permiso_no_puede_crear_prestamo()
    {
        $this->actingAs($this->sinPermisos)
            ->postJson('/api/archivo/prestamos', [])
            ->assertStatus(403);
    }

    /** @test */
    public function sin_permiso_no_puede_aprobar_transferencia()
    {
        $t = OfiArchivoTransferencia::create([
            'tipo' => 'primaria',
            'origen' => 'Dep Origen',
            'responsable_origen_id' => $this->jefe->id,
            'responsable_destino_id' => $this->jefe->id,
            'destino' => 'Dep Destino',
            'fecha_transferencia' => now(),
            'estado' => 'pendiente',
            'expediente_id' => $this->expediente->id,
            'usuario_registro_id' => $this->sinPermisos->id,
        ]);

        $this->actingAs($this->sinPermisos)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", ['estado' => 'aprobada'])
            ->assertStatus(403);

        $t->refresh();
        $this->assertEquals('pendiente', $t->estado);
    }

    /** @test */
    public function sin_permiso_no_puede_crear_expediente()
    {
        $this->actingAs($this->sinPermisos)
            ->postJson('/api/archivo/expedientes', [])
            ->assertStatus(403);
    }

    /** @test */
    public function con_permiso_ve_dashboard_y_plantillas()
    {
        $this->actingAs($this->jefe)
            ->getJson('/api/archivo/dashboard/stats')
            ->assertStatus(200);

        $this->actingAs($this->jefe)
            ->getJson('/api/archivo/plantillas')
            ->assertStatus(200);
    }

    /** @test */
    public function jefe_puede_aprobar_transferencia_pendiente()
    {
        $t = OfiArchivoTransferencia::create([
            'tipo' => 'primaria',
            'origen' => 'Dep Origen',
            'responsable_origen_id' => $this->jefe->id,
            'responsable_destino_id' => $this->jefe->id,
            'destino' => 'Dep Destino',
            'fecha_transferencia' => now(),
            'estado' => 'pendiente',
            'expediente_id' => $this->expediente->id,
            'usuario_registro_id' => $this->jefe->id,
        ]);

        $this->actingAs($this->jefe)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'fecha_transferencia' => now(),
            'estado' => 'aprobada',
            ])
            ->assertStatus(200);

        $t->refresh();
        $this->assertEquals('aprobada', $t->estado);
    }

    /** @test */
    public function rechazo_exige_observaciones()
    {
        $t = OfiArchivoTransferencia::create([
            'tipo' => 'primaria',
            'origen' => 'Dep Origen',
            'responsable_origen_id' => $this->jefe->id,
            'responsable_destino_id' => $this->jefe->id,
            'destino' => 'Dep Destino',
            'fecha_transferencia' => now(),
            'estado' => 'pendiente',
            'expediente_id' => $this->expediente->id,
            'usuario_registro_id' => $this->jefe->id,
        ]);

        $this->actingAs($this->jefe)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'estado' => 'rechazada',
                'observaciones' => '',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function transferencia_decidida_es_inmutable()
    {
        $t = OfiArchivoTransferencia::create([
            'tipo' => 'primaria',
            'origen' => 'Dep Origen',
            'responsable_origen_id' => $this->jefe->id,
            'responsable_destino_id' => $this->jefe->id,
            'destino' => 'Dep Destino',
            'fecha_transferencia' => now(),
            'estado' => 'aprobada',
            'expediente_id' => $this->expediente->id,
            'usuario_registro_id' => $this->jefe->id,
        ]);

        $this->actingAs($this->jefe)
            ->postJson("/api/archivo/transferencias/{$t->id}/aprobar", [
                'estado' => 'rechazada',
                'observaciones' => 'intentando cambiar decisión',
            ])
            ->assertStatus(422);

        $t->refresh();
        $this->assertEquals('aprobada', $t->estado);
    }
}
