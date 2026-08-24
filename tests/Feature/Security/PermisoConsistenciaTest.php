<?php

namespace Tests\Feature\Security;

use Database\Seeders\ControlAcceso\RoleSeeder;
use Database\Seeders\Permisos\PermisosCalidadSeeder;
use Database\Seeders\Permisos\PermisosClasificacionDocumentalSeeder;
use Database\Seeders\Permisos\PermisosConfiguracionSeeder;
use Database\Seeders\Permisos\PermisosControlAccesoSeeder;
use Database\Seeders\Permisos\PermisosDigitalizacionSeeder;
use Database\Seeders\Permisos\PermisosGestionArchivoSeeder;
use Database\Seeders\Permisos\PermisosGestionSeeder;
use Database\Seeders\Permisos\PermisosMiBandejaSeeder;
use Database\Seeders\Permisos\PermisosMiBandejaTempSeeder;
use Database\Seeders\Permisos\PermisosOtrosSeeder;
use Database\Seeders\Permisos\PermisosPlantillasDocumentosSeeder;
use Database\Seeders\Permisos\PermisosPqrsSeeder;
use Database\Seeders\Permisos\PermisosRadicarSeeder;
use Database\Seeders\Permisos\PermisosReportesSeeder;
use Database\Seeders\Permisos\PermisosWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Guardián de consistencia de permisos (previene bugs tipo mismatch seeder↔middleware).
 *
 * Verifica que TODO permiso referenciado por el middleware `can:` en cualquier ruta
 * del sistema exista realmente en la tabla `permissions` después de ejecutar los
 * seeders oficiales. Si este test falla, algún endpoint devolverá 403 para todos
 * los usuarios aunque la UI muestre los botones (el frontend consulta los permisos
 * sembrados, no los usados en código).
 *
 * Nota: las habilidades de política con formato `can:accion,parametro` (p. ej.
 * `ver,documento`) se excluyen porque son métodos de Policy, no permisos Spatie.
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-23
 */
class PermisoConsistenciaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeders oficiales de permisos (mismo orden que DatabaseSeeder).
     */
    private function permisosSeeders(): array
    {
        return [
            PermisosClasificacionDocumentalSeeder::class,
            PermisosControlAccesoSeeder::class,
            PermisosConfiguracionSeeder::class,
            PermisosCalidadSeeder::class,
            PermisosGestionSeeder::class,
            PermisosGestionArchivoSeeder::class,
            PermisosDigitalizacionSeeder::class,
            PermisosReportesSeeder::class,
            PermisosOtrosSeeder::class,
            PermisosMiBandejaSeeder::class,
            PermisosMiBandejaTempSeeder::class,
            PermisosRadicarSeeder::class,
            PermisosPqrsSeeder::class,
            PermisosPlantillasDocumentosSeeder::class,
            PermisosWorkflowsSeeder::class,
        ];
    }

    /** @test */
    public function todo_permiso_usado_en_rutas_existe_en_la_base_de_datos(): void
    {
        $this->seed(RoleSeeder::class);

        foreach ($this->permisosSeeders() as $seeder) {
            $this->seed($seeder);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $usados = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'can:')) {
                    continue;
                }

                $permiso = substr($middleware, 4);

                // Habilidad de política con modelo (ej: ver,documento)
                if (str_contains($permiso, ',')) {
                    continue;
                }

                $usados[$permiso][] = $route->getName() ?: $route->uri();
            }
        }

        $this->assertNotSame([], $usados, 'No se detectó ningún middleware can: en las rutas. El test está roto.');

        $faltantes = [];

        foreach ($usados as $permiso => $rutas) {
            if (! Permission::query()->where('name', $permiso)->exists()) {
                $faltantes[$permiso] = $rutas;
            }
        }

        $this->assertSame(
            [],
            array_keys($faltantes),
            'Permisos usados en rutas pero inexistentes en la tabla permissions (ningún seeder los crea, '.
            'esos endpoints devuelven 403 siempre). Detalle: '.collect($faltantes)
                ->map(fn ($rutas, $permiso) => "[{$permiso}] => ".implode(', ', array_slice($rutas, 0, 4)))
                ->implode(' | ')
        );
    }

    /** @test */
    public function todo_permiso_usado_en_codigo_fuente_existe_en_la_base_de_datos(): void
    {
        $this->seed(RoleSeeder::class);

        foreach ($this->permisosSeeders() as $seeder) {
            $this->seed($seeder);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Escanea literales hasPermissionTo('X') en todo app/.
        // Cubre los checks de permisos en tiempo de ejecución (ABAC, Policies,
        // Form Requests) que el escaneo de rutas no puede ver.
        $literales = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contenido = file_get_contents($file->getPathname());

            preg_match_all("/hasPermissionTo\(\s*'([^']+)'\s*\)/", $contenido, $coincidencias);

            foreach ($coincidencias[1] as $nombre) {
                // Habilidades de política con modelo (ej: ver,documento)
                if (str_contains($nombre, ',')) {
                    continue;
                }

                $literales[$nombre][] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertNotSame([], $literales, 'No se detectó ningún hasPermissionTo literal en app/. El test está roto.');

        $faltantes = [];

        foreach ($literales as $permiso => $archivos) {
            if (! Permission::query()->where('name', $permiso)->exists()) {
                $faltantes[$permiso] = array_unique($archivos);
            }
        }

        $this->assertSame(
            [],
            array_keys($faltantes),
            'Permisos verificados con hasPermissionTo() en código pero inexistentes en la tabla permissions. '.
            'hasPermissionTo lanza excepción cuando el permiso no está registrado (500 o 403 según el catch). '.
            'Detalle: '.collect($faltantes)
                ->map(fn ($archivos, $permiso) => "[{$permiso}] => ".implode(', ', $archivos))
                ->implode(' | ')
        );
    }
}
