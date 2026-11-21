<?php

namespace Tests\Unit;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Feature\Calendario\ZonaHorariaNegocioTest;
use Tests\Feature\Calidad\OrganigramaCiclosTest;
use Tests\Feature\VentanillaUnica\SugerirClasificacionTest;
use Tests\Feature\Workflows\ChecklistTareaLecturaTest;
use Tests\Support\DevelopmentDatabaseGuard;

/** Verifica las barreras de desarrollo sin abrir conexiones ni ejecutar SQL. */
class DevelopmentDatabaseGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_acepta_workflow_transaccional_y_rechaza_modulo_no_auditado(): void
    {
        DevelopmentDatabaseGuard::validateTest(ChecklistTareaLecturaTest::class);
        DevelopmentDatabaseGuard::validateTest(ZonaHorariaNegocioTest::class);
        DevelopmentDatabaseGuard::validateTest(OrganigramaCiclosTest::class);
        DevelopmentDatabaseGuard::validateTest(SugerirClasificacionTest::class);
        $this->expectException(RuntimeException::class);
        DevelopmentDatabaseGuard::validateTest(self::class);
    }

    public function test_todo_el_inventario_de_certificacion_garantiza_rollback(): void
    {
        $classes = require __DIR__.'/../Support/AuditedDevelopmentTests.php';
        $this->assertNotEmpty($classes);
        $this->assertCount(count(array_unique($classes)), $classes);
        foreach ($classes as $class) {
            DevelopmentDatabaseGuard::validateTest($class);
            $this->assertTrue(is_subclass_of($class, \Tests\TestCase::class), $class);
        }
    }

    public static function sentencias(): array
    {
        return [
            'lectura sin transaccion' => ['SELECT 1', 0, true],
            'insertar sin transaccion' => ['INSERT INTO tareas VALUES (1)', 0, false],
            'actualizar sin transaccion' => ['UPDATE tareas SET estado = 1', 0, false],
            'borrar sin transaccion' => ['DELETE FROM tareas', 0, false],
            'insertar con rollback' => ['INSERT INTO tareas VALUES (1)', 1, true],
            'actualizar con savepoint' => ['UPDATE tareas SET estado = 1', 2, true],
            'borrar con rollback' => ['DELETE FROM tareas', 1, true],
            'alterar' => ['ALTER TABLE tareas ADD COLUMN x INT', 1, false],
            'truncar' => ['TRUNCATE tareas', 1, false],
            'crear tabla' => ['CREATE TABLE tareas (id INT)', 1, false],
            'borrar tabla' => ['DROP TABLE tareas', 1, false],
            'commit manual' => ['COMMIT', 1, false],
        ];
    }

    #[DataProvider('sentencias')]
    public function test_intercepta_ddl_y_escrituras_sin_rollback(string $sql, int $level, bool $allowed): void
    {
        $guard = null;
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('ocobo_back');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['total' => 0]);
        $connection->shouldReceive('transactionLevel')->andReturn($level);
        $connection->shouldReceive('beforeExecuting')->once()->with(Mockery::on(function ($callback) use (&$guard) {
            $guard = $callback;

            return is_callable($callback);
        }));
        $manager = Mockery::mock(DatabaseManager::class);
        $manager->shouldReceive('connection')->once()->andReturn($connection);
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('make')->with('db')->once()->andReturn($manager);
        $app->shouldReceive('environment')->with('testing')->once()->andReturn(true);
        DevelopmentDatabaseGuard::protectConnection($app);

        if (! $allowed) {
            $this->expectException(RuntimeException::class);
        }
        $guard($sql, [], $connection);
        if ($allowed) {
            $this->assertTrue(true);
        }
    }
}
