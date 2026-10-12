<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M08-03: expedientes visibles para cualquier titular de "Expedientes -> Ver".
 * M08-04: incorporación de radicados ajenos a un expediente propio.
 * M08-05: edición y retiro de folios en expedientes cerrados.
 */
class AccesoExpedientesTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Gestion de Archivo -> Expedientes -> ';

    private User $user;

    private User $otro;

    private int $depA;

    private int $depB;

    private int $serieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->otro = User::factory()->create(['estado' => 1]);
        foreach (['Ver', 'Crear', 'Editar', 'Incorporar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $this->depA = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep A', 'cod_organico' => 'M83A']);
        $oficina = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Oficina', 'nom_organico' => 'Of A1', 'cod_organico' => 'M83A1', 'parent' => $this->depA]);
        $cargo = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Cargo', 'nom_organico' => 'Cargo A1', 'cod_organico' => 'M83A1C', 'parent' => $oficina]);
        $this->depB = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B', 'cod_organico' => 'M83B']);
        DB::table('users_cargos')->insert(['user_id' => $this->user->id, 'cargo_id' => $cargo, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'S83']);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(int $dependenciaId, string $numero, string $estado = 'abierto'): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => $numero,
            'nombre_expediente' => 'Expediente '.$numero,
            'anio' => 2026,
            'dependencia_id' => $dependenciaId,
            'serie_trd_id' => $this->serieId,
            'estado' => $estado,
            'fecha_apertura' => '2026-10-11',
            'usuario_apertura_id' => $this->otro->id,
        ]);
    }

    public function test_solo_ve_expedientes_de_su_dependencia(): void
    {
        $propio = $this->expediente($this->depA, '2026-M83A-S83-0001');
        $ajeno = $this->expediente($this->depB, '2026-M83B-S83-0001');

        $ids = collect($this->actingAs($this->user)->getJson('/api/archivo/expedientes?per_page=100')
            ->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($propio->id));
        $this->assertFalse($ids->contains($ajeno->id));

        $this->actingAs($this->user)->getJson('/api/archivo/expedientes/'.$propio->id)->assertOk();
        $this->actingAs($this->user)->getJson('/api/archivo/expedientes/'.$ajeno->id)->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/archivo/expedientes/'.$ajeno->id.'/indice')->assertNotFound();
        $this->actingAs($this->user)->putJson('/api/archivo/expedientes/'.$ajeno->id, ['nombre_expediente' => 'X'])->assertNotFound();
    }

    public function test_no_abre_expedientes_de_otra_dependencia(): void
    {
        $this->actingAs($this->user)->postJson('/api/archivo/expedientes', [
            'nombre_expediente' => 'Ajeno', 'dependencia_id' => $this->depB, 'serie_trd_id' => $this->serieId,
        ])->assertForbidden();
    }

    public function test_el_archivo_central_ve_todos(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate(OfiArchivoExpediente::PERMISO_VER_TODOS, 'web'));
        $ajeno = $this->expediente($this->depB, '2026-M83B-S83-0002');

        $this->actingAs($this->user)->getJson('/api/archivo/expedientes/'.$ajeno->id)->assertOk();
    }

    public function test_expediente_cerrado_no_se_edita_ni_pierde_folios(): void
    {
        $cerrado = $this->expediente($this->depA, '2026-M83A-S83-0002', 'cerrado');
        $doc = OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $cerrado->id, 'orden' => 0, 'tipo_documental' => 'Documento',
            'fecha_documento' => '2026-10-11', 'asunto' => 'Doc', 'usuario_id' => $this->otro->id,
        ]);

        $this->actingAs($this->user)->putJson('/api/archivo/expedientes/'.$cerrado->id, ['folios_fisicos' => 99])
            ->assertUnprocessable();
        $this->assertSame(0, (int) $cerrado->fresh()->folios_fisicos);

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$cerrado->id}/documentos/{$doc->id}/soft-delete")
            ->assertUnprocessable();
        $this->assertNotNull(OfiArchivoExpedienteDocumento::find($doc->id));
    }

    /** M08-06: el retiro es lógico; el binario se conserva y un segundo retiro no descuenta folios */
    public function test_retirar_folio_conserva_el_binario_y_es_idempotente(): void
    {
        Storage::fake('archivo_expedientes');
        Storage::disk('archivo_expedientes')->put('m0806/doc.pdf', 'contenido');
        $abierto = $this->expediente($this->depA, '2026-M83A-S83-0004');
        $doc = OfiArchivoExpedienteDocumento::create([
            'expediente_id' => $abierto->id, 'orden' => 0, 'tipo_documental' => 'Documento',
            'fecha_documento' => '2026-10-11', 'asunto' => 'Doc', 'usuario_id' => $this->otro->id,
            'archivo_path' => 'm0806/doc.pdf', 'hash_sha256' => hash('sha256', 'contenido'),
        ]);
        $url = "/api/archivo/expedientes/{$abierto->id}/documentos/{$doc->id}/soft-delete";

        $this->actingAs($this->user)->postJson($url)->assertOk();
        $this->assertFalse($doc->fresh()->activo);
        Storage::disk('archivo_expedientes')->assertExists('m0806/doc.pdf');
        $this->assertSame(0, (int) $abierto->fresh()->total_folios_elec);

        $this->actingAs($this->user)->postJson($url)->assertUnprocessable();
        $this->assertSame(0, (int) $abierto->fresh()->total_folios_elec);
    }

    /** M08-09: el hash sellado al cerrar es el del índice PDF/XML y los folios retirados se marcan */
    public function test_indice_es_verificable_y_marca_folios_retirados(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate(self::PERM.'Cerrar', 'web'));
        $exp = $this->expediente($this->depA, '2026-M83A-S83-0005');
        $base = ['expediente_id' => $exp->id, 'orden' => 0, 'tipo_documental' => 'Documento',
            'fecha_documento' => '2026-10-11', 'usuario_id' => $this->otro->id];
        OfiArchivoExpedienteDocumento::create($base + ['asunto' => 'Activo', 'hash_sha256' => str_repeat('a', 64)]);
        OfiArchivoExpedienteDocumento::create($base + ['asunto' => 'Retirado', 'hash_sha256' => str_repeat('b', 64)])->delete();

        $this->actingAs($this->user)->postJson("/api/archivo/expedientes/{$exp->id}/cerrar", ['motivo_cierre' => 'Fin'])->assertOk();
        $hashIndice = $exp->fresh()->hash_indice;

        $xml = simplexml_load_string($this->get("/api/archivo/expedientes/{$exp->id}/indice-xml")->assertOk()->getContent());
        $this->assertSame($hashIndice, (string) $xml->Integridad->HashIndice);
        $this->assertSame(str_repeat('a', 64), (string) $xml->Documentos->Documento[0]->Huella);
        $this->assertSame('retirado', (string) $xml->Documentos->Documento[1]['estado']);

        $this->get("/api/archivo/expedientes/{$exp->id}/indice")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    private function assertNoIncorpora(string $tipo, int $radicadoId): void
    {
        $propio = $this->expediente($this->depA, '2026-M83A-S83-0003');

        $this->actingAs($this->user)->postJson("/api/archivo/expedientes/{$propio->id}/incorporar", [
            'documentable_type' => $tipo, 'documentable_id' => $radicadoId,
        ])->assertNotFound();
        $this->assertSame(0, OfiArchivoExpedienteDocumento::where('expediente_id', $propio->id)->count());
    }

    public function test_no_incorpora_radicados_recibidos_ajenos(): void
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId(['num_radicado' => 'M83-R-1', 'medio_recep_id' => 1, 'usuario_crea' => $this->otro->id]);
        Schema::enableForeignKeyConstraints();

        $this->assertNoIncorpora('radicado_recibido', $id);
    }

    public function test_incorpora_radicados_recibidos_propios(): void
    {
        $propio = $this->expediente($this->depA, '2026-M83A-S83-0004');
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId(['num_radicado' => 'M83-R-2', 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id]);
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->user)->postJson("/api/archivo/expedientes/{$propio->id}/incorporar", [
            'documentable_type' => 'radicado_recibido', 'documentable_id' => $id,
        ])->assertOk();
    }

    public function test_no_incorpora_radicados_enviados_ajenos(): void
    {
        if (! Schema::hasTable('ventanilla_radica_enviados_responsable')) {
            // Tabla de sesión (sin commit implícito) mientras la BD de pruebas no tenga la migración
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_enviados_responsable LIKE ventanilla_radica_enviados_responsa');
        }
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'M83-E-1', 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->otro->id,
        ]);
        Schema::enableForeignKeyConstraints();

        $this->assertNoIncorpora('radicado_enviado', $id);
    }
}
