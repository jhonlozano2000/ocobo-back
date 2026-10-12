<?php

namespace Tests\Feature\Seguridad;

use App\Http\Middleware\CombinedSecurityMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CombinedSecurityMiddlewareTest extends TestCase
{
    public function test_permite_peticion_con_parametros_normales(): void
    {
        $middleware = new CombinedSecurityMiddleware;
        $request = Request::create('/api/test?query=busqueda-normal&page=1', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_bloquea_peticion_con_null_byte(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('URL contiene caracteres no permitidos.');

        $middleware = new CombinedSecurityMiddleware;
        $request = Request::create('/api/test?param=test%00malicioso', 'GET');

        $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true]);
        });
    }

    public function test_bloquea_peticion_con_path_traversal(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Secuencia de path traversal no permitida.');

        $middleware = new CombinedSecurityMiddleware;
        $request = Request::create('/api/test?file=../../etc/passwd', 'GET');

        $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true]);
        });
    }

    public function test_permite_peticion_con_elipsis_legitima(): void
    {
        $middleware = new CombinedSecurityMiddleware;
        $request = Request::create('/api/test?q=texto...algo', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
