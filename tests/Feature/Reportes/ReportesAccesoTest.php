<?php

namespace Tests\Feature\Reportes;

use App\Mail\ReporteProgramadoMailable;
use App\Models\ReporteProgramado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * REP-001/REP-002: /api/reportes exige el permiso de listado del módulo y devuelve solo las filas
 * que el usuario ve en ese listado; los reportes programados tienen dueño y corren con su alcance.
 */
class ReportesAccesoTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTAR = 'Radicar -> Cores. Recibida -> Listar';

    private const VER_TODOS = 'Radicar -> Cores. Recibida -> Ver Todos';

    private User $user;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        Permission::findOrCreate(self::LISTAR, 'web');
        Permission::findOrCreate(self::VER_TODOS, 'web');
    }

    private function radicado(int $usuarioCrea): string
    {
        $num = 'REP-'.uniqid();
        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_reci')->insert([
            'num_radicado' => $num, 'medio_recep_id' => 1, 'usuario_crea' => $usuarioCrea, 'created_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $num;
    }

    public function test_sin_permiso_del_modulo_responde_403(): void
    {
        $this->actingAs($this->user)->getJson('/api/reportes/unificado?modulo=recibidas')->assertForbidden();
        $this->actingAs($this->user)->getJson('/api/reportes/export?modulo=recibidas&format=csv')->assertForbidden();
    }

    public function test_el_reporte_aplica_el_alcance_del_listado(): void
    {
        $this->user->givePermissionTo(self::LISTAR);
        $propio = $this->radicado($this->user->id);
        $ajeno = $this->radicado($this->otro->id);

        $radicados = collect($this->actingAs($this->user)
            ->getJson('/api/reportes/unificado?modulo=recibidas&per_page=100')
            ->assertOk()->json('data.datos'))->pluck('Radicado');

        $this->assertContains($propio, $radicados);
        $this->assertNotContains($ajeno, $radicados);

        $csv = $this->actingAs($this->user)->get('/api/reportes/export?modulo=recibidas&format=csv')
            ->assertOk()->streamedContent();
        $this->assertStringContainsString($propio, $csv);
        $this->assertStringNotContainsString($ajeno, $csv);
    }

    public function test_la_exportacion_no_se_corta_en_100_filas(): void
    {
        $this->user->givePermissionTo([self::LISTAR, self::VER_TODOS]);
        $total = DB::table('ventanilla_radica_reci')->count();
        for ($i = $total; $i < 101; $i++) {
            $this->radicado($this->user->id);
        }

        $csv = $this->actingAs($this->user)->get('/api/reportes/export?modulo=recibidas&format=csv')
            ->assertOk()->streamedContent();
        $filas = count(array_filter(explode("\n", trim($csv)))) - 1;

        $this->assertSame(min(DB::table('ventanilla_radica_reci')->count(), 5000), $filas);
    }

    public function test_programados_tienen_dueno_y_corren_con_su_alcance(): void
    {
        if (! Schema::hasColumn('reportes_programados', 'user_id')) {
            $this->markTestSkipped('Falta migrar 2026_10_12_120000_add_user_id_to_reportes_programados.');
        }
        Mail::fake();

        $datos = [
            'modulo' => 'recibidas', 'formato' => 'csv', 'periodicidad' => 'daily', 'asunto' => 'Prueba',
            'destinatarios' => ['destino@example.com'], 'proxima_ejecucion' => now()->addDay()->toDateTimeString(),
        ];

        // Sin permiso del módulo no puede programarlo
        $this->actingAs($this->user)->postJson('/api/reportes/programados', $datos)->assertForbidden();

        $this->user->givePermissionTo(self::LISTAR);
        $id = $this->actingAs($this->user)->postJson('/api/reportes/programados', $datos)
            ->assertCreated()->json('data.id');
        $this->assertSame($this->user->id, ReporteProgramado::find($id)->user_id);

        // Otro usuario no lo ve, ni lo edita, ejecuta o elimina
        $this->actingAs($this->otro)->getJson('/api/reportes/programados')->assertOk()
            ->assertJsonMissing(['id' => $id]);
        $this->actingAs($this->otro)->putJson("/api/reportes/programados/{$id}", ['asunto' => 'x'])->assertNotFound();
        $this->actingAs($this->otro)->postJson("/api/reportes/programados/{$id}/ejecutar")->assertNotFound();
        $this->actingAs($this->otro)->deleteJson("/api/reportes/programados/{$id}")->assertNotFound();

        // El dueño lo ejecuta: el CSV ahora sí se adjunta y se envía
        $this->actingAs($this->user)->postJson("/api/reportes/programados/{$id}/ejecutar")->assertOk();
        Mail::assertSent(ReporteProgramadoMailable::class);

        // Si el dueño pierde el permiso, la ejecución se rechaza
        $this->user->revokePermissionTo(self::LISTAR);
        $this->actingAs($this->user->fresh())->postJson("/api/reportes/programados/{$id}/ejecutar")->assertForbidden();
    }
}
