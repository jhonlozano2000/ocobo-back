<?php

namespace Tests\Unit;

use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M04-08: el término se buscaba por nombre exacto ('Peticion', 'Información'...), así que un
 * catálogo con otra grafía ('Petición de información', 'CONSULTA') caía al valor por defecto.
 */
class TerminoLegalPqrsTest extends TestCase
{
    public static function tipos(): array
    {
        return [
            'código del seeder' => ['INF', 'Información', 10],
            'código manda sobre el nombre' => ['CON', 'Otro nombre', 30],
            'código en minúscula' => ['con', 'Consulta', 30],
            'nombre con tildes y sin código' => ['', 'Petición de información', 10],
            'nombre en mayúsculas' => ['', 'CONSULTA', 30],
            'petición con tilde' => ['', 'Petición', 15],
            'tipo de dev sin código' => ['', 'Felicitaciones', 15],
            'tipo desconocido' => ['', 'Pregunta', 15],
        ];
    }

    #[DataProvider('tipos')]
    public function test_resuelve_el_termino_legal(string $codigo, string $nombre, int $esperado): void
    {
        $tipo = new ConfigListaDetalle(['codigo' => $codigo, 'nombre' => $nombre]);

        $this->assertSame($esperado, VentanillaPqrs::diasTerminoPara($tipo));
    }

    public function test_sin_tipo_usa_el_termino_general(): void
    {
        $this->assertSame(15, VentanillaPqrs::diasTerminoPara(null));
    }
}
