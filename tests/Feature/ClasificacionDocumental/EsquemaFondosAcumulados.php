<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Fondo → período → unidad histórica para las pruebas de TVD (M11-02).
 */
trait EsquemaFondosAcumulados
{
    /**
     * BD sin las migraciones de fondos acumulados: las tablas TEMPORARY (sin commit implícito) las suplen
     * y ocultan la clasificacion_documental_tvd anterior, que aún cuelga de la dependencia.
     */
    protected function asegurarEsquema(): void
    {
        if (Schema::hasTable('fondos_acumulados')) {
            return;
        }

        DB::statement('CREATE TEMPORARY TABLE fondos_acumulados (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE, nombre VARCHAR(255) NOT NULL,
            entidad_productora VARCHAR(255) NOT NULL, origen VARCHAR(20) NOT NULL DEFAULT \'propio\', fecha_inicio DATE NULL,
            fecha_fin DATE NULL, descripcion TEXT NULL, user_register BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        DB::statement('CREATE TEMPORARY TABLE fondo_acumulado_periodos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, fondo_id BIGINT UNSIGNED NOT NULL, nombre VARCHAR(255) NOT NULL,
            fecha_inicio DATE NOT NULL, fecha_fin DATE NOT NULL, acto_administrativo VARCHAR(255) NULL, descripcion TEXT NULL,
            aprobado_por BIGINT UNSIGNED NULL, fecha_aprobacion TIMESTAMP NULL, user_register BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        DB::statement('CREATE TEMPORARY TABLE fondo_acumulado_unidades (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, periodo_id BIGINT UNSIGNED NOT NULL, codigo VARCHAR(20) NOT NULL,
            nombre VARCHAR(255) NOT NULL, parent BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            UNIQUE KEY periodo_codigo (periodo_id, codigo))');
        DB::statement('CREATE TEMPORARY TABLE clasificacion_documental_tvd (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(50) NULL, cod VARCHAR(20) NULL, nom VARCHAR(255) NULL,
            descripcion TEXT NULL, soporte VARCHAR(100) NULL, gestion INT NULL, central INT NULL, total_anios INT NULL,
            disposicion_final VARCHAR(100) NULL, procedimiento TEXT NULL, parent BIGINT UNSIGNED NULL,
            periodo_id BIGINT UNSIGNED NOT NULL, unidad_id BIGINT UNSIGNED NOT NULL, user_register BIGINT UNSIGNED NULL,
            estado TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
    }

    protected function usuarioTVD(): User
    {
        $user = User::factory()->create(['estado' => 1]);
        foreach (['TVD -> Listar', 'TVD -> Crear', 'TVD -> Editar', 'TVD -> Eliminar', 'TVD -> Importar', 'TVD -> Versiones', 'Jefe de Archivo'] as $permiso) {
            $user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        return $user;
    }

    protected function fondo(array $attrs = []): int
    {
        return DB::table('fondos_acumulados')->insertGetId($attrs + [
            'codigo' => 'F'.random_int(10000, 99999), 'nombre' => 'Fondo prueba', 'entidad_productora' => 'Entidad',
            'origen' => 'propio', 'fecha_inicio' => '1950-01-01', 'fecha_fin' => '1999-12-31',
        ]);
    }

    protected function periodo(int $fondo, string $inicio = '1950-01-01', string $fin = '1969-12-31'): int
    {
        return DB::table('fondo_acumulado_periodos')->insertGetId([
            'fondo_id' => $fondo, 'nombre' => "Período {$inicio}", 'fecha_inicio' => $inicio, 'fecha_fin' => $fin,
        ]);
    }

    protected function unidad(int $periodo, string $codigo = '100'): int
    {
        return DB::table('fondo_acumulado_unidades')->insertGetId(['periodo_id' => $periodo, 'codigo' => $codigo, 'nombre' => "Unidad {$codigo}"]);
    }

    protected function serie(int $periodo, int $unidad, ?int $registra = null): int
    {
        return DB::table('clasificacion_documental_tvd')->insertGetId([
            'tipo' => 'SerieDocumental', 'cod' => 'V'.random_int(1000, 9999), 'nom' => 'Serie', 'periodo_id' => $periodo,
            'unidad_id' => $unidad, 'user_register' => $registra, 'estado' => 1,
        ]);
    }
}
