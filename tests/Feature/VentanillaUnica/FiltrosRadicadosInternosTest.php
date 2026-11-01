<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El listado de Internos solo atendía búsqueda, ambas fechas juntas y la clasificación: estado, archivos,
 * dependencia y vencimiento filtraban nada, el OCR devolvía un arreglo que la tabla no leía y no había export.
 */
class FiltrosRadicadosInternosTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(
            Permission::findOrCreate('Radicar -> Cores. Interna -> Listar', 'web'),
            Permission::findOrCreate('Radicar -> Cores. Interna -> Ver Todos', 'web'),
        );
    }

    private function interno(string $asunto, array $extra = []): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId(array_merge([
            'num_radicado' => 'FIN-'.uniqid(), 'clasifica_documen_id' => 999999, 'usuario_crea' => $this->user->id,
            'asunto' => $asunto, 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function asuntos(string $query, string $search = 'FINTEST'): array
    {
        $respuesta = $this->actingAs($this->user)
            ->getJson("/api/ventanilla/radica-interno?per_page=100&search={$search}&".$query)->assertOk()->json();

        $asuntos = array_column($respuesta['data']['data'] ?? $respuesta['data'], 'asunto');
        sort($asuntos);

        return $asuntos;
    }

    public function test_fechas_de_radicacion_y_de_vencimiento(): void
    {
        $this->interno('FINTEST viejo', ['created_at' => '2026-01-10 09:00:00', 'fec_venci' => '2026-02-01']);
        $this->interno('FINTEST nuevo', ['created_at' => '2026-03-20 17:30:00', 'fec_venci' => '2026-04-15']);

        $this->assertSame(['FINTEST nuevo'], $this->asuntos('fecha_desde=2026-02-01'));
        $this->assertSame(['FINTEST viejo'], $this->asuntos('fecha_hasta=2026-01-10'));
        $this->assertSame(['FINTEST nuevo'], $this->asuntos('fecha_venci_desde=2026-04-15'));
        $this->assertSame(['FINTEST viejo'], $this->asuntos('fecha_venci_hasta=2026-02-01'));
    }

    public function test_estado_archivos_y_ocr(): void
    {
        $this->interno('FINTEST en proceso', ['estado_trabajo' => 'EN_PROCESO', 'archivo_digital' => 'x/a.pdf', 'ocr' => 'memorando zqxint']);
        $conAdjunto = $this->interno('FINTEST con adjunto', ['estado_trabajo' => 'RECIBIDO']);
        $this->interno('FINTEST sin nada', ['estado_trabajo' => 'RECIBIDO']);
        DB::table('ventanilla_radica_internos_archivos')->insert([
            'radicado_id' => $conAdjunto, 'archivo' => 'x/b.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(['FINTEST en proceso'], $this->asuntos('estado_trabajo=EN_PROCESO'));
        $this->assertSame(['FINTEST con adjunto', 'FINTEST en proceso'], $this->asuntos('con_archivos=si'));
        $this->assertSame(['FINTEST sin nada'], $this->asuntos('con_archivos=no'));
        $this->assertSame(['FINTEST en proceso'], $this->asuntos('ocr=zqxint'));
        $this->assertSame(['FINTEST en proceso'], $this->asuntos('', 'zqxint'));
        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-interno?estado_trabajo=pendiente')->assertStatus(422);
        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-interno?per_page=5000')->assertStatus(422);
    }

    public function test_dependencia_incluye_sus_oficinas_y_clasificacion_por_serie(): void
    {
        $dependencia = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Dependencia', 'nom_organico' => 'Dep FINTEST', 'cod_organico' => 'I'.random_int(1000, 9999),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $oficina = DB::table('calidad_organigrama')->insertGetId([
            'tipo' => 'Oficina', 'nom_organico' => 'Ofi FINTEST', 'cod_organico' => 'O'.random_int(1000, 9999),
            'parent' => $dependencia, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::disableForeignKeyConstraints();
        $serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'FIN'.random_int(100, 999), 'nom' => 'Serie FINTEST',
            'user_register' => $this->user->id, 'estado' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tipoDoc = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'TipoDocumento', 'cod' => 'FIN'.random_int(100, 999), 'nom' => 'Tipo FINTEST', 'parent' => $serie,
            'user_register' => $this->user->id, 'estado' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $this->interno('FINTEST de la oficina', ['dependencia_origen_id' => $oficina, 'clasifica_documen_id' => $tipoDoc]);
        $this->interno('FINTEST de otra parte');

        $this->assertSame(['FINTEST de la oficina'], $this->asuntos("dependencia_id={$dependencia}"));
        $this->assertSame(['FINTEST de la oficina'], $this->asuntos("clasifica_documen_id={$serie}"));

        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-interno/dependencias')
            ->assertOk()->assertJsonFragment(['id' => $dependencia, 'nom_organico' => 'Dep FINTEST']);
        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-interno/clasificaciones')
            ->assertOk()->assertJsonFragment(['id' => $serie, 'nom' => 'Serie FINTEST']);
    }

    public function test_el_export_aplica_los_mismos_filtros(): void
    {
        $this->interno('FINTEST en proceso', ['estado_trabajo' => 'EN_PROCESO', 'fec_docu' => '2026-02-01']);
        $this->interno('FINTEST recibido', ['estado_trabajo' => 'RECIBIDO']);

        $contenido = $this->actingAs($this->user)
            ->get('/api/ventanilla/radica-interno/export?format=csv&search=FINTEST&estado_trabajo=EN_PROCESO')
            ->assertOk()->streamedContent();
        $filas = array_map('str_getcsv', array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF"))));

        $this->assertCount(2, $filas);
        $this->assertSame('FINTEST en proceso', $filas[1][2]);
        $this->assertSame('2026-02-01', $filas[1][7]);
        $this->assertSame('EN_PROCESO', $filas[1][9]);
    }
}
