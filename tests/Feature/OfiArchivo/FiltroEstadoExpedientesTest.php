<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B03 (diseño de expedientes): el filtro de estado de la lista se ignoraba.
 */
class FiltroEstadoExpedientesTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private int $depId;

    private int $serieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver Todos', 'web'));
        $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Ver', 'web'));

        Schema::disableForeignKeyConstraints();
        $this->depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep F', 'cod_organico' => 'B03D']);
        $this->serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $this->user->id, 'tipo' => 'Serie', 'cod' => 'B03']);
        Schema::enableForeignKeyConstraints();
    }

    private function expediente(string $numero, string $estado): OfiArchivoExpediente
    {
        return OfiArchivoExpediente::create([
            'numero_expediente' => $numero,
            'nombre_expediente' => 'Expediente '.$numero,
            'anio' => 2026,
            'dependencia_id' => $this->depId,
            'serie_trd_id' => $this->serieId,
            'estado' => $estado,
            'fecha_apertura' => '2026-10-18',
            'usuario_apertura_id' => $this->user->id,
        ]);
    }

    public function test_filtra_por_estado(): void
    {
        $abierto = $this->expediente('2026-B03D-B03-0001', 'abierto');
        $cerrado = $this->expediente('2026-B03D-B03-0002', 'cerrado');

        $ids = collect($this->actingAs($this->user)
            ->getJson('/api/archivo/expedientes?per_page=100&search=B03D&estado=cerrado')
            ->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($cerrado->id));
        $this->assertFalse($ids->contains($abierto->id));
    }

    public function test_rechaza_estado_desconocido(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/archivo/expedientes?estado=archivado')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }
}
