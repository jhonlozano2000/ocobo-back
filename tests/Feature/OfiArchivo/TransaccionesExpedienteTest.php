<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B25 (diseño de expedientes): las salidas tempranas y los 404 de cerrar/incorporar/retirar
 * dejaban la transacción abierta; la validación pasa a FormRequests.
 */
class TransaccionesExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $depId;

    private int $serieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Ver Todos', 'Ver', 'Cerrar', 'Incorporar', 'Editar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $this->depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep T', 'cod_organico' => 'B25D']);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'B25']);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(string $estado): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B25D-B25-'.uniqid(),
            'nombre_expediente' => 'Expediente B25',
            'anio' => 2026,
            'dependencia_id' => $this->depId,
            'serie_trd_id' => $this->serieId,
            'estado' => $estado,
            'fecha_apertura' => '2026-10-18',
            'usuario_apertura_id' => $this->user->id,
        ]);
    }

    public function test_cerrar_un_expediente_cerrado_no_deja_la_transaccion_abierta(): void
    {
        $nivel = DB::transactionLevel();
        $cerrado = $this->expediente('cerrado');

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$cerrado->id}/cerrar", ['motivo_cierre' => 'Trámite finalizado'])
            ->assertStatus(422);

        $this->assertSame($nivel, DB::transactionLevel());
    }

    public function test_cerrar_un_expediente_inexistente_no_deja_la_transaccion_abierta(): void
    {
        $nivel = DB::transactionLevel();

        $this->actingAs($this->user)
            ->postJson('/api/archivo/expedientes/999999999/cerrar', ['motivo_cierre' => 'Trámite finalizado'])
            ->assertNotFound();

        $this->assertSame($nivel, DB::transactionLevel());
    }

    public function test_incorporar_en_un_expediente_cerrado_no_deja_la_transaccion_abierta(): void
    {
        $nivel = DB::transactionLevel();
        $cerrado = $this->expediente('cerrado');

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$cerrado->id}/incorporar", [
                'documentable_id' => 1,
                'documentable_type' => 'radicado_recibido',
            ])
            ->assertStatus(422);

        $this->assertSame($nivel, DB::transactionLevel());
    }

    public function test_retirar_un_folio_inexistente_no_deja_la_transaccion_abierta(): void
    {
        $nivel = DB::transactionLevel();
        $abierto = $this->expediente('abierto');

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$abierto->id}/documentos/999999999/soft-delete")
            ->assertNotFound();

        $this->assertSame($nivel, DB::transactionLevel());
    }

    public function test_validaciones_con_mensajes_en_espanol(): void
    {
        $abierto = $this->expediente('abierto');

        $this->actingAs($this->user)
            ->postJson("/api/archivo/expedientes/{$abierto->id}/cerrar", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_cierre' => 'El motivo del cierre es obligatorio.']);

        $this->actingAs($this->user)
            ->post("/api/archivo/expedientes/{$abierto->id}/archivos", [
                'archivos' => [UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf')],
                'tipo' => 'tipo_documental',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_documental' => 'El nombre del tipo documental es requerido.']);
    }
}
