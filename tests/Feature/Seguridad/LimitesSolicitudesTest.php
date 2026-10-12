<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * M02-05 / M03-01 / M16-11: además del límite general (throttle:api), la radicación, las
 * notificaciones por correo, las subidas y la escritura de terceros tienen su limitador estricto.
 */
class LimitesSolicitudesTest extends TestCase
{
    public static function rutas(): array
    {
        return [
            ['POST', 'api/ventanilla/radica-enviada', 'radicacion'],
            ['DELETE', 'api/ventanilla/radica-enviada', 'radicacion'],
            ['POST', 'api/ventanilla/radica-enviada/{id}/notificacion', 'radicacion'],
            ['POST', 'api/ventanilla/radica-enviada/{id}/notificacion-tercero', 'radicacion'],
            ['POST', 'api/ventanilla/radica-enviada/{id}/archivos/digital/upload', 'uploads'],
            ['POST', 'api/ventanilla/radica-enviada/{id}/archivos/digital/ocr/recargar', 'uploads'],
            ['POST', 'api/ventanilla/radica-enviada/{id}/archivos/adjuntos/upload-adjuntos', 'uploads'],
            ['POST', 'api/ventanilla/radica-interno', 'radicacion'],
            ['POST', 'api/ventanilla/radica-interno/{id}/notificacion', 'radicacion'],
            ['POST', 'api/ventanilla/radica-interno/{id}/archivos/digital/upload', 'uploads'],
            ['POST', 'api/ventanilla/radica-interno/{id}/archivos/digital/ocr/recargar', 'uploads'],
            ['POST', 'api/ventanilla/radica-interno/{id}/archivos/adjuntos/upload-adjuntos', 'uploads'],
            ['POST', 'api/gestion/terceros', 'terceros'],
            ['PUT', 'api/gestion/terceros/{tercero}', 'terceros'],
            ['DELETE', 'api/gestion/terceros/{tercero}', 'terceros'],
        ];
    }

    /** @dataProvider rutas */
    public function test_ruta_tiene_su_limitador(string $metodo, string $uri, string $limitador): void
    {
        $ruta = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === $uri && in_array($metodo, $r->methods(), true));

        $this->assertNotNull($ruta, "$metodo $uri no existe");
        $this->assertContains('throttle:'.$limitador, $ruta->gatherMiddleware(), "$metodo $uri sin throttle:$limitador");
    }
}
