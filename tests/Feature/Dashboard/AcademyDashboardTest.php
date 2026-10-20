<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * /api/dashboard/academy ("Mi gestión"): el resumen se arma con los radicados en los que el
 * usuario autenticado es responsable, sin contar los asignados a otros funcionarios.
 */
class AcademyDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_responde_con_la_estructura_del_dashboard(): void
    {
        $user = User::factory()->create(['estado' => 1]);

        $this->actingAs($user)->getJson('/api/dashboard/academy')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'welcome' => ['user_name', 'progress_text', 'items', 'time_spendings' => ['labels', 'donut_series', 'total_hours', 'change']],
                'topics' => ['series', 'labels', 'data1', 'data2'],
                'instructors',
                'top_courses',
                'upcoming_webinar' => ['title', 'desc', 'date', 'duration'],
                'assignment_progress',
                'courses_table',
            ]]);
    }

    public function test_mi_gestion_solo_cuenta_los_radicados_asignados_al_usuario(): void
    {
        $user = User::factory()->create(['estado' => 1, 'nombres' => 'Funcionaria']);
        $ajeno = User::factory()->create(['estado' => 1]);

        Schema::disableForeignKeyConstraints();
        $this->radicadoAsignadoA($user, now()->addDays(3)->toDateString());
        $this->radicadoAsignadoA($ajeno, now()->addDay()->toDateString());
        Schema::enableForeignKeyConstraints();

        $datos = $this->actingAs($user)->getJson('/api/dashboard/academy')->assertOk()->json('data');

        $this->assertSame('Funcionaria', $datos['welcome']['user_name']);
        $this->assertSame('1', $datos['welcome']['time_spendings']['total_hours']);
        $this->assertSame('1', collect($datos['welcome']['items'])->firstWhere('title', 'Pendientes')['value']);
        $this->assertSame(1, collect($datos['assignment_progress'])->firstWhere('modulo', 'recibidos')['tasks']);
        // El próximo vencimiento es el propio, aunque el del otro funcionario venza antes
        $this->assertSame('recibidos', $datos['upcoming_webinar']['modulo']);
        $this->assertStringContainsString('Asunto de '.$user->id, $datos['upcoming_webinar']['desc']);
    }

    /** Crea un radicado recibido abierto y deja a $user como su responsable. */
    private function radicadoAsignadoA(User $user, string $vence): void
    {
        // Cargo inactivo: el usuario no ve por jerarquía los radicados del otro
        $cargoId = DB::table('users_cargos')->insertGetId([
            'user_id' => $user->id, 'cargo_id' => 1, 'fecha_inicio' => now()->subYear()->toDateString(),
            'fecha_fin' => now()->subDay()->toDateString(), 'estado' => 0,
        ]);
        $reciId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'ACAD-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $user->id,
            'asunto' => 'Asunto de '.$user->id, 'fec_venci' => $vence, 'estado_trabajo' => 'RECIBIDO',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ventanilla_radica_reci_responsa')->insert([
            'radica_reci_id' => $reciId, 'users_cargos_id' => $cargoId, 'custodio' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
