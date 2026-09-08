<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja - Grupos Colaborativos.
 *
 * Foco principal: prevención de IDOR horizontal (F1). Los endpoints operan
 * por ID con permisos globales, por lo que DEBE verificarse membresía del
 * grupo (creador o revisor/firmante/proyector/aprobador).
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-23
 */
class GruposColaborativosAccessTest extends TestCase
{

    protected User $creador;
    protected User $miembro;
    protected User $ajeno;
    protected VentanillaRadicaInterno $radicado;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Mi Bandeja - Grupos Colaborativos -> Ver',
            'Mi Bandeja - Grupos Colaborativos -> Crear',
            'Mi Bandeja - Grupos Colaborativos -> Editar',
            'Mi Bandeja - Grupos Colaborativos -> Eliminar',
            'Mi Bandeja - Grupos Colaborativos -> Enviar Tramite',
            'Mi Bandeja - Grupos Colaborativos -> Gestionar Miembros',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // Los TRES usuarios tienen TODOS los permisos globales: así se demuestra
        // que los 403 provienen de la membresía del grupo y no de permisos.
        $role = Role::firstOrCreate(['name' => 'ColaboradorMB']);
        $role->givePermissionTo($permisos);

        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dirección MB',
            'cod_organico' => 'DIRMB01',
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Analista MB',
            'cod_organico' => 'CMB0001',
            'parent' => $dependencia->id,
        ]);

        $this->creador = User::factory()->create();
        $this->creador->assignRole($role);

        $this->miembro = User::factory()->create();
        $this->miembro->assignRole($role);

        $this->ajeno = User::factory()->create();
        $this->ajeno->assignRole($role);

        // La FK de mi_bandeja_temp.radicado_id apunta SIEMPRE a ventanilla_radica_reci
        // (independientemente del radicado_tipo declarado). Radicado mínimo suficiente.
        $listaMedios = \App\Models\Configuracion\ConfigLista::create([
            'nombre' => 'Tipos de Recepción',
            'cod' => 'L-MEDIO',
        ]);
        $detalleMedio = \App\Models\Configuracion\ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo',
            'codigo' => 'CORR',
            'estado' => 1,
        ]);

        $this->radicadoReci = VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-'.uniqid(),
            'usuario_crea' => $this->creador->id,
            'medio_recep_id' => $detalleMedio->id,
            'fec_radicado' => now(),
            'estado_trabajo' => 'recibido',
        ]);
    }

    /**
     * Crea un grupo como creador y agrega a $this->miembro como revisor.
     */
    private function crearGrupoConMiembro(): MiBandejaTemp
    {
        $response = $this->actingAs($this->creador)
            ->postJson('/api/mi-bandeja/grupos-colaborativos', [
                'nombre' => 'Grupo de prueba',
                'radicado_id' => $this->radicadoReci->id,
                'radicado_tipo' => 'recibido',
            ]);

        $response->assertStatus(201);

        $grupoId = $response->json('data.id');

        MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $grupoId,
            'user_id' => $this->miembro->id,
            'cargo_id' => $this->miembro->userCargo->cargo_id ?? null,
        ]);

        return MiBandejaTemp::findOrFail($grupoId);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $this->getJson('/api/mi-bandeja/grupos-colaborativos')->assertStatus(401);
        $this->postJson('/api/mi-bandeja/grupos-colaborativos', [])->assertStatus(401);
    }

    /** @test */
    public function validacion_falla_sin_nombre_y_sin_radicado()
    {
        // F3: antes devolvía 500 por catch genérico
        $this->actingAs($this->creador)
            ->postJson('/api/mi-bandeja/grupos-colaborativos', [])
            ->assertStatus(422);
    }

    /** @test */
    public function creador_puede_crear_grupo_y_aparece_en_su_index()
    {
        $grupo = $this->crearGrupoConMiembro();

        $this->assertDatabaseHas('mi_bandeja_temp', [
            'id' => $grupo->id,
            'usua_crea_id' => $this->creador->id,
        ]);

        $index = $this->actingAs($this->creador)
            ->getJson('/api/mi-bandeja/grupos-colaborativos')
            ->assertStatus(200);

        $ids = collect($index->json('data'))->pluck('id');

        $this->assertContains((int) $grupo->id, $ids);
    }

    /** @test */
    public function miembro_del_grupo_puede_verlo()
    {
        $grupo = $this->crearGrupoConMiembro();

        $this->actingAs($this->miembro)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$grupo->id}")
            ->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function ajeno_con_permisos_no_puede_ver_el_grupo() // IDOR
    {
        $grupo = $this->crearGrupoConMiembro();

        $this->actingAs($this->ajeno)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$grupo->id}")
            ->assertStatus(403);
    }

    /** @test */
    public function ajeno_no_puede_actualizar_el_grupo() // IDOR
    {
        $grupo = $this->crearGrupoConMiembro();

        $this->actingAs($this->ajeno)
            ->putJson("/api/mi-bandeja/grupos-colaborativos/{$grupo->id}", [
                'nombre' => 'Hackeado',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('mi_bandeja_temp', ['id' => $grupo->id, 'nombre' => 'Hackeado']);
    }

    /** @test */
    public function ajeno_no_puede_marcar_terminado() // IDOR
    {
        $grupo = $this->crearGrupoConMiembro();

        $this->actingAs($this->ajeno)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$grupo->id}/marcar-terminado")
            ->assertStatus(403);
    }

    /** @test */
    public function ajeno_con_permiso_eliminar_no_puede_anular_grupo_ajeno()
    {
        $grupo = $this->crearGrupoConMiembro();

        $this->actingAs($this->ajeno)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$grupo->id}/anular")
            ->assertStatus(403);
    }
}
