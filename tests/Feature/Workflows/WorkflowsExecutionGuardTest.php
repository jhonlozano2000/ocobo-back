<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use App\Services\Workflows\WorkflowExecutionService;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de guards de ejecución y ownership del módulo Workflows.
 *
 * Cubre hallazgos de auditoría:
 * - #7: WorkflowPolicy conectada (ownership: solo creador o Administrador)
 * - #8: guardarCanvas bloqueado con instancias en curso
 * - #12: ejecutarNodo exige nodo_actual (no saltar secuencia)
 * - #13: detenerInstancia solo desde en_curso
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-25
 */
class WorkflowsExecutionGuardTest extends TestCase
{

    protected User $creador;

    protected User $otroUsuario;

    protected Workflow $workflow;

    protected WorkflowNodo $nodoInicio;

    protected WorkflowNodo $nodoFin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Workflows -> Workflows -> Listar',
            'Workflows -> Workflows -> Mostrar',
            'Workflows -> Workflows -> Crear',
            'Workflows -> Workflows -> Editar',
            'Workflows -> Workflows -> Eliminar',
            'Workflows -> Instancias -> Consultar',
            'Workflows -> Instancias -> Ejecutar',
        ];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Rol SIN 'Administrador' para probar ownership estricto
        $rol = Role::firstOrCreate(['name' => 'WfEjecutor Test']);
        $rol->givePermissionTo($permisos);

        $this->creador = User::factory()->create();
        $this->creador->assignRole($rol);
        $this->otroUsuario = User::factory()->create();
        $this->otroUsuario->assignRole($rol);

        $this->workflow = Workflow::create([
            'nombre' => 'WF Guard '.Str::random(4),
            'creador_user_id' => $this->creador->id,
            'estado' => 'activo',
        ]);

        $this->nodoInicio = WorkflowNodo::create([
            'workflow_id' => $this->workflow->id,
            'tipo' => 'inicio',
            'titulo' => 'Inicio',
            'orden_ejecucion' => 1,
        ]);
        $this->nodoFin = WorkflowNodo::create([
            'workflow_id' => $this->workflow->id,
            'tipo' => 'fin',
            'titulo' => 'Fin',
            'orden_ejecucion' => 2,
        ]);
    }

    /** @test */
    public function no_creador_no_puede_editar_workflow_ajeno(): void
    {
        $res = $this->actingAs($this->otroUsuario)
            ->putJson("/api/workflows/workflows/{$this->workflow->id}", [
                'nombre' => 'Hackeado',
            ]);

        $res->assertStatus(403);
    }

    /** @test */
    public function no_creador_no_puede_eliminar_workflow_ajeno(): void
    {
        $res = $this->actingAs($this->otroUsuario)
            ->deleteJson("/api/workflows/workflows/{$this->workflow->id}");

        $res->assertStatus(403);
        $this->assertDatabaseHas('workflows', ['id' => $this->workflow->id]);
    }

    /** @test */
    public function creador_puede_editar_su_workflow(): void
    {
        $res = $this->actingAs($this->creador)
            ->putJson("/api/workflows/workflows/{$this->workflow->id}", [
                'nombre' => 'Renombrado por dueño',
            ]);

        $res->assertStatus(200);
    }

    /** @test */
    public function canvas_bloqueado_con_instancia_en_curso(): void
    {
        $this->actingAs($this->creador);
        app(WorkflowExecutionService::class)->iniciarInstancia($this->workflow->id, $this->creador->id);

        $res = $this->actingAs($this->creador)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/canvas", [
                'nodos' => [
                    ['id' => 'a1', 'client_id' => 'a1', 'tipo' => 'inicio', 'titulo' => 'Nuevo inicio', 'posicion_x' => 0, 'posicion_y' => 0],
                    ['id' => 'a2', 'client_id' => 'a2', 'tipo' => 'fin', 'titulo' => 'Nuevo fin', 'posicion_x' => 200, 'posicion_y' => 0],
                ],
                'conexiones' => [],
            ]);

        $res->assertStatus(422);
        $this->assertDatabaseHas('workflow_nodos', ['id' => $this->nodoInicio->id]);
    }

    /** @test */
    public function canvas_permite_guardar_sin_instancias_activas(): void
    {
        $res = $this->actingAs($this->creador)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/canvas", [
                'nodos' => [
                    ['id' => 'a1', 'client_id' => 'a1', 'tipo' => 'inicio', 'titulo' => 'Inicio v2', 'posicion_x' => 0, 'posicion_y' => 0],
                    ['id' => 'a2', 'client_id' => 'a2', 'tipo' => 'fin', 'titulo' => 'Fin v2', 'posicion_x' => 200, 'posicion_y' => 0],
                ],
                'conexiones' => [
                    ['id' => 'c1', 'nodo_origen_id' => 'a1', 'nodo_destino_id' => 'a2'],
                ],
            ]);

        $res->assertStatus(200);
        $this->assertSame(2, WorkflowNodo::where('workflow_id', $this->workflow->id)->count());
    }

    /** @test */
    public function no_se_puede_ejecutar_nodo_fuera_de_secuencia(): void
    {
        $this->actingAs($this->creador);
        $instancia = app(WorkflowExecutionService::class)
            ->iniciarInstancia($this->workflow->id, $this->creador->id);

        // Intentar ejecutar el nodo FIN directamente (saltando la secuencia)
        $res = $this->actingAs($this->creador)
            ->postJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$instancia->id}/ejecutar", [
                'nodo_id' => $this->nodoFin->id,
            ]);

        $res->assertStatus(422);
    }

    /** @test */
    public function no_se_puede_detener_instancia_completada(): void
    {
        $this->actingAs($this->creador);
        $instancia = app(WorkflowExecutionService::class)
            ->iniciarInstancia($this->workflow->id, $this->creador->id);

        $instancia->update(['estado' => 'completada']);

        $res = $this->actingAs($this->creador)
            ->patchJson("/api/workflows/workflows/{$this->workflow->id}/instancias/{$instancia->id}/detener");

        $res->assertStatus(422);
        $this->assertDatabaseHas('workflow_instancias', [
            'id' => $instancia->id,
            'estado' => 'completada',
        ]);
    }
}
