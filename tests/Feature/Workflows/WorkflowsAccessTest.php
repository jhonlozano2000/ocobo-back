<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use Tests\TestCase;

/**
 * Tests feature M20 Workflows — acceso base y validaciones.
 *
 * Los acciones sobre tareas/instancias están protegidos por TareaPolicy
 * (verificados por el guardián de permisos en su escaneo de código fuente).
 * Esta suite cubre: autenticación, creación con/sin validación y los
 * endpoints "mis-*" del usuario autenticado.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-24
 */
class WorkflowsAccessTest extends TestCase
{

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // El módulo Workflows autoriza vía Policies que exigen estos permisos
        $permisos = [
            'Workflows -> Workflows -> Listar',
            'Workflows -> Workflows -> Crear',
            'Workflows -> Tareas -> Listar',
            'Workflows -> Instancias -> Consultar',
        ];

        foreach ($permisos as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p]);
        }

        $rol = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Gestor Workflows']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $this->getJson('/api/workflows/workflows')->assertStatus(401);
        $this->postJson('/api/workflows/workflows', [])->assertStatus(401);
        $this->getJson('/api/workflows/mis-tareas')->assertStatus(401);
        $this->getJson('/api/workflows/mis-instancias')->assertStatus(401);
    }

    /** @test */
    public function usuario_autenticado_lista_workflows_y_mis_recursos()
    {
        // Sin permisos específicos: el módulo aún no filtra por permiso, pero
        // las colecciones propias del usuario deben responder 200 vacías.
        $this->actingAs($this->user)->getJson('/api/workflows/workflows')->assertStatus(200);

        $this->actingAs($this->user)
            ->getJson('/api/workflows/mis-tareas')
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->actingAs($this->user)
            ->getJson('/api/workflows/mis-instancias')
            ->assertStatus(200);
    }

    /** @test */
    public function creacion_falla_sin_nombre() // F3: 422, antes 500
    {
        $this->actingAs($this->user)
            ->postJson('/api/workflows/workflows', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function creacion_valida_crea_workflow()
    {
        $this->actingAs($this->user)
            ->postJson('/api/workflows/workflows', [
                'nombre' => 'Flujo de prueba',
                'descripcion' => 'Creado desde test',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('workflows', ['nombre' => 'Flujo de prueba']);
    }

    /** @test */
    public function tiempo_finalizacion_fuera_de_rango_falla()
    {
        $this->actingAs($this->user)
            ->postJson('/api/workflows/workflows', [
                'nombre' => 'Flujo inválido',
                'tiempo_finalizacion_horas' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tiempo_finalizacion_horas']);
    }
}
