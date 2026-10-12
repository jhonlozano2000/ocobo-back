<?php

namespace Tests\Feature\Configuracion;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * M18-06: todo comando programado en routes/console.php debe existir; uno inexistente
 * hace fallar el scheduler en cada ejecución.
 */
class SchedulerComandosTest extends TestCase
{
    public function test_todos_los_comandos_programados_existen(): void
    {
        $registrados = array_keys(Artisan::all());
        $faltantes = [];
        $revisados = 0;

        foreach (app(Schedule::class)->events() as $evento) {
            // La línea es "<php>" "artisan" cmd (Windows) o '<php>' 'artisan' cmd (Unix)
            if (! preg_match('/artisan["\']?\s+["\']?([\w:-]+)/', (string) $evento->command, $m)) {
                continue;
            }

            $revisados++;

            if (! in_array($m[1], $registrados, true)) {
                $faltantes[] = $m[1];
            }
        }

        $this->assertGreaterThan(0, $revisados, 'No se reconoció ningún comando programado');
        $this->assertSame([], $faltantes, 'Comandos programados inexistentes: '.implode(', ', $faltantes));
    }

    /**
     * M18-03: en despliegue multi-nodo cada tarea debe correr en un solo servidor.
     */
    public function test_todas_las_tareas_corren_en_un_solo_servidor(): void
    {
        $eventos = app(Schedule::class)->events();
        $this->assertNotEmpty($eventos);

        foreach ($eventos as $evento) {
            $this->assertTrue($evento->onOneServer, 'Sin onOneServer(): '.$evento->command);
        }
    }
}
