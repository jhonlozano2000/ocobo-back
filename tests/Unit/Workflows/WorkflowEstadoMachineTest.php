<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Workflows\WorkflowEstadoMachine;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de la máquina de estados de tareas Workflow.
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-25
 */
class WorkflowEstadoMachineTest extends TestCase
{
    /** @test */
    public function pendiente_solo_avanza_a_en_curso_o_cancelada(): void
    {
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('pendiente', 'en_curso'));
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('pendiente', 'cancelada'));

        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('pendiente', 'completada'));
        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('pendiente', 'vencida'));
    }

    /** @test */
    public function en_curso_puede_completar_vencer_o_cancelar(): void
    {
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('en_curso', 'completada'));
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('en_curso', 'vencida'));
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('en_curso', 'cancelada'));
        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('en_curso', 'pendiente'));
    }

    /** @test */
    public function vencida_permite_reactivar_a_en_curso(): void
    {
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('vencida', 'en_curso'));
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('vencida', 'cancelada'));
        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('vencida', 'completada'));
    }

    /** @test */
    public function estados_terminales_no_transicionan(): void
    {
        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('completada', 'en_curso'));
        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('completada', 'cancelada'));
        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('cancelada', 'en_curso'));
    }

    /** @test */
    public function es_terminal_detecta_estados_finales(): void
    {
        $this->assertTrue(WorkflowEstadoMachine::esTerminal('completada'));
        $this->assertTrue(WorkflowEstadoMachine::esTerminal('cancelada'));

        $this->assertFalse(WorkflowEstadoMachine::esTerminal('pendiente'));
        $this->assertFalse(WorkflowEstadoMachine::esTerminal('en_curso'));
        $this->assertFalse(WorkflowEstadoMachine::esTerminal('vencida'));
    }

    /** @test */
    public function normalizar_mapea_en_progreso_a_en_curso(): void
    {
        $this->assertSame('en_curso', WorkflowEstadoMachine::normalizar('en_progreso'));
        $this->assertSame('pendiente', WorkflowEstadoMachine::normalizar('pendiente'));
        $this->assertSame('otro', WorkflowEstadoMachine::normalizar('otro'));
    }

    /** @test */
    public function puede_transitar_normaliza_el_estado_origen(): void
    {
        // en_progreso es alias de en_curso: debe poder completarse
        $this->assertTrue(WorkflowEstadoMachine::puedeTransitar('en_progreso', 'completada'));
        $this->assertFalse(WorkflowEstadoMachine::puedeTransitar('en_progreso', 'pendiente'));
    }

    /** @test */
    public function transiciones_permitidas_de_estado_desconocido_es_vacio(): void
    {
        $this->assertSame([], WorkflowEstadoMachine::transicionesPermitidas('estado_inexistente'));
    }

    /** @test */
    public function necesita_vencimiento_solo_para_estados_activos_con_fecha_pasada(): void
    {
        $ayer = now()->subDay()->toDateString();
        $manana = now()->addDay()->toDateString();

        $this->assertTrue(WorkflowEstadoMachine::necesitaVencimiento('pendiente', $ayer));
        $this->assertTrue(WorkflowEstadoMachine::necesitaVencimiento('en_curso', $ayer));
        $this->assertFalse(WorkflowEstadoMachine::necesitaVencimiento('pendiente', $manana));
        $this->assertFalse(WorkflowEstadoMachine::necesitaVencimiento('pendiente', null));

        // Estados no activos nunca requieren vencimiento
        $this->assertFalse(WorkflowEstadoMachine::necesitaVencimiento('completada', $ayer));
        $this->assertFalse(WorkflowEstadoMachine::necesitaVencimiento('cancelada', $ayer));
        $this->assertFalse(WorkflowEstadoMachine::necesitaVencimiento('vencida', $ayer));
    }
}
