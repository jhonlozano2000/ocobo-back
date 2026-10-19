<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Diseño de expedientes §6.2 [A.4.2.10]: al responder una PQRS se verifica que la petición y la
 * respuesta estén en un expediente. Se avisa, pero la respuesta no se bloquea.
 */
class VerificacionCierrePqrsTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $radicado;

    private int $pqrs;

    private int $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('ventanilla_pqrs', 'es_anonimo') || ! Schema::hasColumn('ofi_archivo_expedientes', 'nivel_acceso')) {
            $this->markTestSkipped('Requiere la reserva de identidad de la PQRS (M04-03) y la migración 2026_10_19_100000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Radicar -> PQRSF -> Editar', 'Gestion de Archivo -> Expedientes -> Ver'] as $permiso) {
            $this->user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $tipo = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Petición']);
        $this->radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'CPQ-'.uniqid(), 'asunto' => 'Petición', 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pqrs = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $this->radicado, 'tipo_pqrs_id' => $tipo, 'estado_tramite' => 'En Tramite',
            'fecha_vencimiento' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expediente = DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'CPQ-'.uniqid(), 'nombre_expediente' => 'PQRS', 'anio' => 2026, 'dependencia_id' => 999999,
            'serie_trd_id' => 1, 'estado' => 'abierto', 'nivel_acceso' => 'publico', 'fecha_apertura' => '2026-10-01',
            'usuario_apertura_id' => $this->user->id,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    private function responder(): int
    {
        $enviado = DB::table('ventanilla_radica_enviados')->insertGetId([
            'num_radicado' => 'CPQ-E-'.uniqid(), 'clasifica_documen_id' => 1, 'medio_enviado_id' => 1,
            'tercero_id' => 1, 'tipo_respuesta_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        DB::table('ventanilla_radica_enviados_respuestas')->insert(['radica_enviado_id' => $enviado, 'radica_reci_id' => $this->radicado]);

        return $enviado;
    }

    private function incorporar(string $clase, int $id): void
    {
        $folio = DB::table('ofi_archivo_expedientes_documentos')->where('expediente_id', $this->expediente)->count() + 1;
        DB::table('ofi_archivo_expedientes_documentos')->insert([
            'expediente_id' => $this->expediente, 'documentable_type' => $clase, 'documentable_id' => $id,
            'numero_folio' => $folio, 'orden' => $folio, 'tipo_documental' => 'Oficio', 'fecha_documento' => '2026-10-01',
            'asunto' => 'Folio', 'usuario_id' => $this->user->id, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function marcarRespondida()
    {
        return $this->actingAs($this->user)->putJson("/api/ventanilla/pqrs/{$this->pqrs}/estado", ['estado_tramite' => 'Respondida'])->assertOk();
    }

    public function test_responder_sin_archivar_avisa_pero_no_bloquea(): void
    {
        $this->marcarRespondida()->assertJsonPath('message',
            'Estado actualizado a: Respondida. Pendiente de archivo: La petición no está en ningún expediente. La PQRS no tiene un radicado enviado de respuesta.');
        $this->assertSame('Respondida', DB::table('ventanilla_pqrs')->where('id', $this->pqrs)->value('estado_tramite'));
    }

    public function test_con_peticion_y_respuesta_archivadas_no_hay_aviso(): void
    {
        $enviado = $this->responder();
        $this->incorporar(VentanillaRadicaReci::class, $this->radicado);

        $verificacion = $this->actingAs($this->user)
            ->getJson("/api/archivo/expedientes/sugerencias?origen_tipo=pqrs&origen_id={$this->pqrs}")
            ->assertOk()->json('data.verificacion_cierre');
        $this->assertFalse($verificacion['completo']);
        $this->assertSame(['La respuesta no está en ningún expediente.'], $verificacion['faltantes']);
        $this->assertSame([['id' => $enviado, 'en_expediente' => false]], array_map(
            fn ($r) => ['id' => $r['id'], 'en_expediente' => $r['en_expediente']], $verificacion['respuestas']));
        $this->assertSame(['verificable' => false, 'correos_registrados' => 0], $verificacion['constancia_notificacion']);

        $this->incorporar(VentanillaRadicaEnviados::class, $enviado);
        $this->marcarRespondida()->assertJsonPath('message', 'Estado actualizado a: Respondida');
    }
}
