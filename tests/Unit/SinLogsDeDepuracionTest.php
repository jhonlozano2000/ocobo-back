<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * M04-10: quedaban logs de depuración ("DEBUG PQRS ...") que escribían en el log
 * el detalle de la solicitud del ciudadano (datos personales, Ley 1581).
 */
class SinLogsDeDepuracionTest extends TestCase
{
    public function test_el_codigo_no_tiene_logs_de_depuracion(): void
    {
        $encontrados = [];
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }
            if (preg_match_all("/Log::\\w+\\(\\s*'DEBUG\\b/", file_get_contents($archivo->getPathname()), $m)) {
                $encontrados[] = $archivo->getFilename();
            }
        }

        $this->assertSame([], $encontrados);
    }
}
