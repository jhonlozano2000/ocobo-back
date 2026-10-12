<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * M01-03: VentanillaRadicaReciPolicy nunca se invocaba y su update() llamaba un método inexistente.
 * La autorización de recibidos vive en los middleware de ruta; esta prueba impide que se agregue
 * una ruta del módulo sin `can:` ni `acceso_radicado`.
 */
class RutasRecibidosAutorizadasTest extends TestCase
{
    public function test_toda_ruta_de_recibidos_tiene_middleware_de_autorizacion(): void
    {
        $sinAutorizacion = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_starts_with($ruta->getActionName(), 'App\Http\Controllers\VentanillaUnica\Recibidos'))
            ->reject(fn ($ruta) => collect($ruta->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && preg_match('/^(can:|acceso_radicado:)/', $m)))
            ->map(fn ($ruta) => implode('|', $ruta->methods()).' '.$ruta->uri())
            ->values()
            ->all();

        $this->assertNotEmpty(Route::getRoutes()->getByAction('App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciController@index'));
        $this->assertSame([], $sinAutorizacion);
    }

    public function test_el_radicado_recibido_no_tiene_policy_muerta(): void
    {
        $this->assertNull(Gate::getPolicyFor(VentanillaRadicaReci::class));
    }
}
