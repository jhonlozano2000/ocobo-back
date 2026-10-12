<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * RBAC-003: radica_reci_id de los documentos colaborativos se validaba solo con `exists`, así que
 * cualquiera podía vincular (y ver luego en su documento) un radicado al que no tiene acceso.
 */
class DocumentoColaborativoRadicadoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private User $creador;

    private User $ajeno;

    private int $radicadoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);

        Schema::disableForeignKeyConstraints();
        $this->radicadoId = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'RBAC3C-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->creador->id,
            'asunto' => 'Radicado reservado',
        ]);
        Schema::enableForeignKeyConstraints();
    }

    public function test_solo_se_vinculan_radicados_accesibles(): void
    {
        $datos = ['titulo' => 'Respuesta', 'radica_reci_id' => $this->radicadoId];

        $this->actingAs($this->ajeno)->postJson(self::URL, $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('radica_reci_id');
        $this->actingAs($this->ajeno)->postJson(self::URL.'/importar-html', $datos + ['contenido' => '<p>x</p>'])
            ->assertUnprocessable()->assertJsonValidationErrors('radica_reci_id');

        $this->actingAs($this->creador)->postJson(self::URL, $datos)->assertCreated();
    }
}
