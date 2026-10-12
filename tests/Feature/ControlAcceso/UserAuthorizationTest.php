<?php

namespace Tests\Feature\ControlAcceso;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RBAC-001 / M14-001 / M17-01: la gestión de usuarios y cargos exige permiso explícito.
 */
class UserAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIX = '/api/control-acceso';

    protected User $funcionario;

    protected User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'Control de acceso - Usuarios -> Listar',
            'Control de acceso - Usuarios -> Mostrar',
            'Control de acceso - Usuarios -> Editar',
            'Control de acceso - Usuarios -> Asignar Roles',
            'Control de acceso - Cargos -> Listar',
            'Control de acceso - Cargos -> Asignar',
            'Control de acceso - Cargos -> Finalizar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->funcionario = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
    }

    public function test_usuario_sin_permiso_no_lista_ni_ve_usuarios(): void
    {
        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/users')->assertForbidden();
        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/users/'.$this->otro->id)->assertForbidden();
        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/users/'.$this->otro->id.'/perfil-completo')->assertForbidden();
    }

    public function test_usuario_sin_permiso_no_asigna_cargos_ni_otros_permisos(): void
    {
        $this->actingAs($this->funcionario)
            ->postJson(self::PREFIX.'/user-cargos/asignar', ['user_id' => $this->otro->id, 'cargo_id' => 1])
            ->assertForbidden();

        $this->actingAs($this->funcionario)
            ->putJson(self::PREFIX.'/users/'.$this->funcionario->id.'/otros-permisos', ['permisos' => ['Mi Bandeja -> Puede firmar']])
            ->assertForbidden();
    }

    public function test_selector_disponible_sin_permiso_y_sin_datos_sensibles(): void
    {
        $response = $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/users/selector')->assertOk();

        $usuario = collect($response->json('data'))->firstWhere('id', $this->otro->id);
        $this->assertNotNull($usuario);
        $this->assertEqualsCanonicalizing(
            ['id', 'nombres', 'apellidos', 'email', 'avatar_url', 'cargo', 'oficina', 'dependencia'],
            array_keys($usuario)
        );
    }

    /**
     * RBAC-002: actividad, historial, conexiones, permisos y sesiones de otro usuario exigen permiso.
     */
    public function test_detalle_de_otro_usuario_exige_permiso(): void
    {
        foreach (['actividad', 'historial', 'conexiones', 'permisos', 'sessions'] as $recurso) {
            $this->actingAs($this->funcionario)
                ->getJson(self::PREFIX.'/users/'.$this->otro->id.'/'.$recurso)
                ->assertForbidden();
        }

        $this->funcionario->givePermissionTo('Control de acceso - Usuarios -> Mostrar');
        $this->actingAs($this->funcionario->fresh())
            ->getJson(self::PREFIX.'/users/'.$this->otro->id.'/permisos')
            ->assertOk();
    }

    public function test_usuario_puede_ver_su_propio_perfil_sin_permiso(): void
    {
        $this->actingAs($this->funcionario)
            ->getJson(self::PREFIX.'/users/'.$this->funcionario->id.'/permisos')
            ->assertOk();
    }

    public function test_editar_sin_asignar_roles_no_puede_cambiar_roles(): void
    {
        $rol = Role::findOrCreate('Rol Prueba RBAC', 'web');
        $this->funcionario->givePermissionTo('Control de acceso - Usuarios -> Editar');

        $this->actingAs($this->funcionario)
            ->putJson(self::PREFIX.'/users/'.$this->otro->id, ['roles' => [$rol->name]])
            ->assertForbidden();

        $this->assertFalse($this->otro->fresh()->hasRole($rol->name));
    }

    public function test_nadie_puede_cambiar_sus_propios_roles(): void
    {
        $rol = Role::findOrCreate('Rol Prueba RBAC', 'web');
        $this->funcionario->givePermissionTo([
            'Control de acceso - Usuarios -> Editar',
            'Control de acceso - Usuarios -> Asignar Roles',
        ]);

        $this->actingAs($this->funcionario)
            ->putJson(self::PREFIX.'/users/'.$this->funcionario->id, ['roles' => [$rol->name]])
            ->assertForbidden();

        $this->assertFalse($this->funcionario->fresh()->hasRole($rol->name));
    }
}
