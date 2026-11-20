<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

/** Restringe las pruebas en desarrollo al módulo auditado y evita cambios de esquema. */
final class DevelopmentDatabaseGuard
{
    /**
     * Falla antes de iniciar Laravel si la prueba puede migrar o no garantiza rollback.
     *
     * @param  class-string  $class
     */
    public static function validateTest(string $class): void
    {
        $traits = class_uses_recursive($class);
        if (! str_starts_with($class, 'Tests\\Feature\\Workflows\\')
            || ! isset($traits[DatabaseTransactions::class])
            || array_intersect([RefreshDatabase::class, DatabaseMigrations::class, DatabaseTruncation::class], $traits)) {
            throw new RuntimeException('BD de desarrollo: solo se permiten pruebas Workflows auditadas con DatabaseTransactions, sin migraciones ni truncado.');
        }
    }

    /** Verifica el destino y motor transaccional; bloquea DDL y escrituras fuera de transacción. */
    public static function protectConnection(Application $app): void
    {
        $connection = $app->make('db')->connection();
        if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'ocobo_back') {
            throw new RuntimeException('Destino de pruebas inesperado; se requiere testing sobre MySQL ocobo_back.');
        }

        $nonTransactional = $connection->selectOne(
            "SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'",
            ['ocobo_back']
        );
        if ((int) $nonTransactional->total !== 0) {
            throw new RuntimeException('La BD contiene tablas sin rollback InnoDB; ejecución cancelada.');
        }

        $connection->beforeExecuting(static function (string $query, array $bindings, $db): void {
            if (preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME|GRANT|REVOKE|COMMIT|BEGIN|START|LOCK|UNLOCK)\b/i', $query)) {
                throw new RuntimeException('Operación SQL no permitida en las pruebas sobre desarrollo.');
            }
            if (! preg_match('/^\s*(?:SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $query) && $db->transactionLevel() < 1) {
                throw new RuntimeException('Escritura de prueba fuera de DatabaseTransactions; ejecución cancelada.');
            }
        });
    }
}
