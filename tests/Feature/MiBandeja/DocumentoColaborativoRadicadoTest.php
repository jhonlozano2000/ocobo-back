<?php

namespace Tests\Feature\MiBandeja;

use App\Events\MiBandeja\TempReci\UsuarioConectado;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoController;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

    public function test_la_lectura_responde_antes_de_notificar_presencia(): void
    {
        $id = $this->actingAs($this->creador)->postJson(self::URL, ['titulo' => 'Lectura sin Reverb'])
            ->assertCreated()->json('data.id');
        $documento = Documento::findOrFail($id);
        $request = Request::create(self::URL."/{$id}");
        $request->setUserResolver(fn () => $this->creador);
        Event::fake([UsuarioConectado::class]);

        $response = app(DocumentoController::class)
            ->show($request, $documento);

        $this->assertSame(200, $response->getStatusCode());
        Event::assertNotDispatched(UsuarioConectado::class);
        app(DeferredCallbackCollection::class)->invoke();
        Event::assertDispatched(UsuarioConectado::class);

        $request->setUserResolver(fn () => $this->ajeno);
        $rechazo = app(DocumentoController::class)->show($request, $documento);
        $this->assertSame(403, $rechazo->getStatusCode());
        app(DeferredCallbackCollection::class)->invoke();
        Event::assertDispatchedTimes(UsuarioConectado::class, 1);
    }
}
