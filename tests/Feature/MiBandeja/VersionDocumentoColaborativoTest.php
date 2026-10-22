<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\Version;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Version::crearVersion llamaba a `$documento->limitarVersiones()`, que no existe en Documento
 * (es estático en Version): todo versionado (autoguardado, restaurar, crear versión) daba 500.
 */
class VersionDocumentoColaborativoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crear_version_funciona_y_respeta_el_maximo(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['estado' => 1]);
        $id = $this->actingAs($user)->postJson('/api/mi-bandeja/comunicaciones-recibidas/documentos', ['titulo' => 'Versiones'])
            ->assertCreated()->json('data.id');
        $documento = Documento::findOrFail($id);

        foreach (range(1, 3) as $n) {
            $this->assertNotNull(Version::crearVersion($documento, ['type' => 'doc', 'n' => $n], $user));
        }
        $this->assertSame(1, Version::limitarVersiones($documento, 2));
        $this->assertSame(2, $documento->versiones()->count());
    }

    /** El historial y las sugerencias pedían la columna users.name, que no existe: respondían 500. */
    public function test_el_historial_y_las_sugerencias_traen_el_nombre_del_usuario(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Ruiz']);
        $base = '/api/mi-bandeja/comunicaciones-recibidas/documentos';
        $id = $this->actingAs($user)->postJson($base, ['titulo' => 'Historial'])->assertCreated()->json('data.id');
        Version::crearVersion(Documento::findOrFail($id), ['type' => 'doc'], $user);

        $this->actingAs($user)->getJson("{$base}/{$id}/versiones")
            ->assertOk()
            ->assertJsonPath('data.0.usuario.nombres', 'Ana')
            ->assertJsonPath('data.0.usuario.apellidos', 'Ruiz');

        $this->actingAs($user)->getJson("{$base}/{$id}/sugerencias")->assertOk();
    }
}
