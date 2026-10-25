<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 2.11: pestaña de respuestas del radicado enviado (recibidos a los que responde). El listado trae lo
 * que muestra la pestaña; asociar exige ver el recibido y desasociar revierte la respuesta del recibido.
 */
class RespuestasEnviadoTest extends TestCase
{
    use DatabaseTransactions;

    private const PERMISOS = ['Radicar -> Cores. Enviada -> Editar', 'Radicar -> Cores. Recibida -> Listar'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(self::PERMISOS);
        Schema::disableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function enviado(): array
    {
        $num = 'ENV-'.uniqid();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => $num, 'clasifica_documen_id' => 1, 'tercero_id' => 1, 'medio_enviado_id' => 1,
            'tipo_respuesta_id' => 1, 'usuario_crea' => $this->user->id, 'asunto' => 'Respuesta',
            'estado_firma' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$id, $num];
    }

    private function recibido(?User $creador = null, array $extra = []): int
    {
        return DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'REC-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => ($creador ?? $this->user)->id,
            'asunto' => 'Solicitud de información', 'created_at' => now(), 'updated_at' => now(),
        ] + $extra);
    }

    private function ruta(int $enviadoId, string $resto = ''): string
    {
        return "/api/ventanilla/radica-enviada/{$enviadoId}/recibidos{$resto}";
    }

    public function test_asociar_marca_el_recibido_y_el_listado_trae_numero_asunto_y_estado(): void
    {
        [$enviadoId, $num] = $this->enviado();
        $recibido = $this->recibido();

        $this->actingAs($this->user)->postJson($this->ruta($enviadoId), ['radica_reci_ids' => [$recibido]])
            ->assertCreated();

        $fila = DB::table('ventanilla_radica_reci')->find($recibido);
        $this->assertSame($num, $fila->radicado_respuesta);
        $this->assertSame('TRAMITADO', $fila->estado_trabajo);

        $datos = $this->actingAs($this->user)->getJson($this->ruta($enviadoId))->assertOk()->json('data');
        $this->assertCount(1, $datos);
        $this->assertSame(['id', 'num_radicado', 'asunto', 'estado_trabajo', 'created_at'], array_keys($datos[0]));
        $this->assertSame($fila->num_radicado, $datos[0]['num_radicado']);
        $this->assertSame('Solicitud de información', $datos[0]['asunto']);
        $this->assertSame('TRAMITADO', $datos[0]['estado_trabajo']);

        // Repetir la asociación no la duplica
        $this->actingAs($this->user)->postJson($this->ruta($enviadoId), ['radica_reci_ids' => [$recibido]])->assertCreated();
        $this->assertSame(1, DB::table('ventanilla_radica_enviados_respuestas')->where('radica_enviado_id', $enviadoId)->count());
    }

    public function test_no_puede_asociar_un_recibido_que_no_ve(): void
    {
        [$enviadoId] = $this->enviado();
        $propio = $this->recibido();
        $ajeno = $this->recibido(User::factory()->create());

        $this->actingAs($this->user)->postJson($this->ruta($enviadoId), ['radica_reci_ids' => [$propio, $ajeno]])
            ->assertForbidden();

        // No se asocia ninguno ni se toca el estado del ajeno
        $this->assertSame(0, DB::table('ventanilla_radica_enviados_respuestas')->where('radica_enviado_id', $enviadoId)->count());
        $this->assertNull(DB::table('ventanilla_radica_reci')->where('id', $ajeno)->value('estado_trabajo'));
    }

    public function test_desasociar_revierte_la_respuesta_y_el_estado_del_recibido(): void
    {
        [$enviadoId] = $this->enviado();
        $recibido = $this->recibido();
        $this->actingAs($this->user)->postJson($this->ruta($enviadoId), ['radica_reci_ids' => [$recibido]])->assertCreated();

        $this->actingAs($this->user)->deleteJson($this->ruta($enviadoId, "/{$recibido}"))
            ->assertOk()->assertJsonPath('data', []);

        $fila = DB::table('ventanilla_radica_reci')->find($recibido);
        $this->assertNull($fila->radicado_respuesta);
        $this->assertNotSame('TRAMITADO', $fila->estado_trabajo);

        $this->actingAs($this->user)->deleteJson($this->ruta($enviadoId, "/{$recibido}"))->assertNotFound();
    }

    public function test_desasociar_conserva_la_respuesta_de_otro_enviado(): void
    {
        [$primero, $numPrimero] = $this->enviado();
        [$segundo, $numSegundo] = $this->enviado();
        $recibido = $this->recibido();
        $this->actingAs($this->user)->postJson($this->ruta($primero), ['radica_reci_ids' => [$recibido]])->assertCreated();
        $this->actingAs($this->user)->postJson($this->ruta($segundo), ['radica_reci_ids' => [$recibido]])->assertCreated();
        $this->assertSame($numSegundo, DB::table('ventanilla_radica_reci')->where('id', $recibido)->value('radicado_respuesta'));

        $this->actingAs($this->user)->deleteJson($this->ruta($segundo, "/{$recibido}"))->assertOk();

        $fila = DB::table('ventanilla_radica_reci')->find($recibido);
        $this->assertSame($numPrimero, $fila->radicado_respuesta);
        $this->assertSame('TRAMITADO', $fila->estado_trabajo);
    }
}
