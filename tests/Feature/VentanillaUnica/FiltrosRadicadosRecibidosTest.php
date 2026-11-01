<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El listado de Recibidos mandaba los filtros en camelCase y el back solo atendía la búsqueda: fechas,
 * medio, estado y el resto filtraban únicamente la página visible.
 */
class FiltrosRadicadosRecibidosTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(
            Permission::findOrCreate('Radicar -> Cores. Recibida -> Listar', 'web'),
            Permission::findOrCreate('Radicar -> Cores. Recibida -> Ver Todos', 'web'),
        );
    }

    private function recibido(string $asunto, array $extra = []): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId(array_merge([
            'num_radicado' => 'FIL-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
            'asunto' => $asunto, 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function asuntos(string $query): array
    {
        $respuesta = $this->actingAs($this->user)->getJson('/api/ventanilla/radica-recibida?per_page=100&search=FILTEST&'.$query)
            ->assertOk()->json();

        $filas = $respuesta['data']['data'] ?? $respuesta['data'];
        $asuntos = array_column($filas, 'asunto');
        sort($asuntos);

        return $asuntos;
    }

    public function test_fechas_por_separado_y_hasta_inclusivo(): void
    {
        $this->recibido('FILTEST viejo', ['created_at' => '2026-01-10 09:00:00']);
        $this->recibido('FILTEST nuevo', ['created_at' => '2026-03-20 17:30:00']);

        $this->assertSame(['FILTEST nuevo'], $this->asuntos('fecha_desde=2026-02-01'));
        $this->assertSame(['FILTEST viejo'], $this->asuntos('fecha_hasta=2026-01-10'));
        $this->assertSame(['FILTEST nuevo'], $this->asuntos('fecha_desde=2026-03-20&fecha_hasta=2026-03-20'));
    }

    public function test_estado_medio_y_tipo_de_persona(): void
    {
        Schema::disableForeignKeyConstraints();
        $juridico = DB::table('gestion_terceros')->insertGetId([
            'num_docu_nit' => 'NIT'.uniqid(), 'nom_razo_soci' => 'Empresa SA', 'tipo' => 'Juridico',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $this->recibido('FILTEST tramitado', ['estado_trabajo' => 'TRAMITADO', 'medio_recep_id' => 17, 'tercero_id' => $juridico]);
        $this->recibido('FILTEST recibido', ['estado_trabajo' => 'RECIBIDO']);

        $this->assertSame(['FILTEST tramitado'], $this->asuntos('estado_trabajo=TRAMITADO'));
        $this->assertSame(['FILTEST tramitado'], $this->asuntos('medio_recep_id=17'));
        $this->assertSame(['FILTEST tramitado'], $this->asuntos('tercero_tipo=Juridico'));
        $this->assertSame(['FILTEST tramitado'], $this->asuntos('search=Empresa'));
    }

    public function test_archivos_y_vencimiento(): void
    {
        $conArchivo = $this->recibido('FILTEST vencido', ['fec_venci' => now()->subDays(3)->toDateString()]);
        $this->recibido('FILTEST vigente', ['fec_venci' => now()->addDays(30)->toDateString()]);
        DB::table('ventanilla_radica_reci_archivos')->insert([
            'radicado_id' => $conArchivo, 'archivo' => 'x.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(['FILTEST vencido'], $this->asuntos('con_archivos=si'));
        $this->assertSame(['FILTEST vigente'], $this->asuntos('con_archivos=no'));
        $this->assertSame(['FILTEST vencido'], $this->asuntos('vencimiento=vencidos'));
        $this->assertSame(['FILTEST vigente'], $this->asuntos('vencimiento=vigentes'));
    }

    public function test_responsable_y_dependencia(): void
    {
        $dependencia = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Dependencia', 'nom_organico' => 'Dep FILTEST', 'cod_organico' => 'F'.random_int(1000, 9999),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cargo = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Cargo', 'nom_organico' => 'Cargo FILTEST', 'cod_organico' => 'C'.random_int(1000, 9999),
            'parent' => $dependencia, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $responsable = User::factory()->create(['nombres' => 'Zoraida', 'apellidos' => 'Quintero']);
        $userCargo = DB::table('users_cargos')->insertGetId([
            'user_id' => $responsable->id, 'cargo_id' => $cargo, 'fecha_inicio' => now()->toDateString(),
            'estado' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $asignado = $this->recibido('FILTEST asignado');
        $this->recibido('FILTEST sin asignar');
        DB::table('ventanilla_radica_reci_responsa')->insert([
            'radica_reci_id' => $asignado, 'users_cargos_id' => $userCargo, 'custodio' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(['FILTEST asignado'], $this->asuntos('responsable=zoraida quin'));
        $this->assertSame(['FILTEST asignado'], $this->asuntos("dependencia_id={$dependencia}"));

        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-recibida/dependencias')
            ->assertOk()->assertJsonFragment(['id' => $dependencia, 'nom_organico' => 'Dep FILTEST']);
    }

    public function test_el_export_aplica_los_mismos_filtros_y_trae_el_estado(): void
    {
        $this->recibido('FILTEST tramitado', ['estado_trabajo' => 'TRAMITADO', 'fec_docu' => '2026-02-01']);
        $this->recibido('FILTEST recibido', ['estado_trabajo' => 'RECIBIDO']);

        $contenido = $this->actingAs($this->user)
            ->get('/api/ventanilla/radica-recibida/export?format=csv&search=FILTEST&estado_trabajo=TRAMITADO')
            ->assertOk()->streamedContent();
        $filas = array_map('str_getcsv', array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF"))));

        $this->assertCount(2, $filas);
        $this->assertSame('FILTEST tramitado', $filas[1][2]);
        $this->assertSame('2026-02-01', $filas[1][6]);
        $this->assertSame(trim($this->user->nombres.' '.$this->user->apellidos), $filas[1][8]);
        $this->assertSame('TRAMITADO', $filas[1][9]);
    }
}
