<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Pqrs\VentanillaPqrsComentario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PqrsComentarioTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $user2;
    protected UserCargo $userCargo;
    protected VentanillaPqrs $pqrs;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Spatie permission cache for test isolation
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Radicar -> PQRSF -> Listar',
            'Radicar -> PQRSF -> Crear',
            'Radicar -> PQRSF -> Mostrar',
            'Radicar -> PQRSF -> Comentar',
            'Radicar -> PQRSF -> ComentarCrear',
            'Radicar -> PQRSF -> ComentarEditar',
            'Radicar -> PQRSF -> ComentarEliminar',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $role = Role::firstOrCreate(['name' => 'Ventanilla']);
        $role->givePermissionTo($permisos);

        $dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dirección General',
            'cod_organico' => 'DIR-001',
        ]);

        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo',
            'nom_organico' => 'Analista PQRS',
            'cod_organico' => 'CPQRS-001',
            'parent' => $dependencia->id,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->user2 = User::factory()->create();
        $this->user2->assignRole($role);

        $this->userCargo = UserCargo::create([
            'user_id' => $this->user->id,
            'cargo_id' => $cargo->id,
            'fecha_inicio' => now(),
            'estado' => true,
        ]);

        $lista = ConfigLista::create(['cod' => 'TipoPQRSF', 'nombre' => 'Tipo PQRS']);
        $tipoPqrs = ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'PQR', 'nombre' => 'PQRS']);

        $this->pqrs = VentanillaPqrs::create([
            'tipo_pqrs_id' => $tipoPqrs->id,
            'prioridad' => 'Normal',
            'estado_tramite' => 'Pendiente',
            'asunto' => 'PQRS de prueba para comentarios',
            'detalle_solicitud' => 'Detalle de prueba',
            'usuario_crea' => $this->user->id,
            'clasificacion_documental_trd_id' => null,
            'gestion_tercero_id' => null,
            'config_divi_poli_id_afectado' => null,
            'fechor_tramite' => now(),
            'fecha_vencimiento' => now()->addDays(15),
        ]);
    }

    /** @test */
    public function puede_crear_comentario_raiz()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/comentarios", [
                'contenido' => 'Este es un comentario de prueba',
                'es_nota_interna' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('ventanilla_pqrs_comentarios', 1);
    }

    /** @test */
    public function puede_crear_respuesta_a_comentario()
    {
        $comentarioPadre = VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Comentario padre',
            'es_nota_interna' => false,
        ]);

        $response = $this->actingAs($this->user2)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/comentarios", [
                'contenido' => 'Esta es una respuesta',
                'parent_id' => $comentarioPadre->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('ventanilla_pqrs_comentarios', 2);
    }

    /** @test */
    public function puede_listar_comentarios_de_un_pqrs()
    {
        VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Comentario 1',
        ]);

        VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user2->id,
            'contenido' => 'Comentario 2',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/pqrs/{$this->pqrs->id}/comentarios");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function puede_obtener_comentario_individual()
    {
        $comentario = VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Comentario individual',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/pqrs/comentarios/{$comentario->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    /** @test */
    public function puede_actualizar_propio_comentario()
    {
        $comentario = VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Contenido original',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/ventanilla/pqrs/comentarios/{$comentario->id}", [
                'contenido' => 'Contenido actualizado',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $comentario->refresh();
        $this->assertEquals('Contenido actualizado', $comentario->contenido);
    }

    /** @test */
    public function no_puede_actualizar_comentario_ajeno()
    {
        $comentario = VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Comentario del usuario 1',
        ]);

        $response = $this->actingAs($this->user2)
            ->putJson("/api/ventanilla/pqrs/comentarios/{$comentario->id}", [
                'contenido' => 'Intento de hackeo',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function puede_resolver_comentario()
    {
        $comentario = VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Comentario a resolver',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/comentarios/{$comentario->id}/resolver");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $comentario->refresh();
        $this->assertNotNull($comentario->fecha_resolucion);
    }

    /** @test */
    public function puede_eliminar_propio_comentario()
    {
        $comentario = VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Comentario a eliminar',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/ventanilla/pqrs/comentarios/{$comentario->id}");

        $response->assertStatus(200);
        $this->assertDatabaseCount('ventanilla_pqrs_comentarios', 0);
    }

    /** @test */
    public function no_puede_eliminar_comentario_ajeno()
    {
        $comentario = VentanillaPqrsComentario::create([
            'pqrs_id' => $this->pqrs->id,
            'user_id' => $this->user->id,
            'contenido' => 'Comentario del usuario 1',
        ]);

        $response = $this->actingAs($this->user2)
            ->deleteJson("/api/ventanilla/pqrs/comentarios/{$comentario->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function requiere_autenticacion()
    {
        $response = $this->getJson("/api/ventanilla/pqrs/{$this->pqrs->id}/comentarios");
        $response->assertStatus(401);

        $response = $this->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/comentarios", []);
        $response->assertStatus(401);
    }

    /** @test */
    public function falla_si_contenido_vacio()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/ventanilla/pqrs/{$this->pqrs->id}/comentarios", [
                'contenido' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contenido']);
    }
}
