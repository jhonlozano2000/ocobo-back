<?php

namespace Tests\Unit;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * M08-16 (CWE-209): las respuestas 500 devolvían el mensaje de la excepción (SQL, rutas internas).
 */
class ErroresServidorSinDetalleTest extends TestCase
{
    private Container $previousContainer;

    private $previousFacadeApplication;

    /** El contrato HTTP del trait no requiere arrancar Laravel ni acceder a MySQL. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();

        $container = new Container;
        Container::setInstance($container);
        Facade::clearResolvedInstance('log');
        Facade::setFacadeApplication($container);
        $container->instance('config', new Repository(['app' => ['debug' => false]]));
        $container->instance('request', Request::create('https://ocobo.test/api/prueba'));

        $factory = $this->createMock(ResponseFactory::class);
        $factory->method('json')->willReturnCallback(
            fn ($data = [], $status = 200, $headers = [], $options = 0) => new JsonResponse($data, $status, $headers, $options)
        );
        $container->instance(ResponseFactory::class, $factory);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('log');
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function respuestas(): object
    {
        return new class
        {
            use ApiResponseTrait;

            public function error(int $code)
            {
                return $this->errorResponse('Error al guardar', 'SQLSTATE[23000]: Integrity constraint violation', $code);
            }
        };
    }

    public function test_sin_debug_el_detalle_del_500_va_al_log(): void
    {
        config(['app.debug' => false]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Error al guardar',
            $this->callback(fn ($context) => str_contains($context['error'], 'SQLSTATE')
                && $context['url'] === 'https://ocobo.test/api/prueba')
        );
        Log::swap($logger);

        $json = $this->respuestas()->error(500)->getData(true);

        $this->assertSame('Error al guardar', $json['message']);
        $this->assertArrayNotHasKey('errors', $json);
    }

    public function test_con_debug_o_errores_de_cliente_se_conserva_el_detalle(): void
    {
        config(['app.debug' => true]);
        $this->assertArrayHasKey('errors', $this->respuestas()->error(500)->getData(true));

        config(['app.debug' => false]);
        $this->assertArrayHasKey('errors', $this->respuestas()->error(422)->getData(true));
    }
}
