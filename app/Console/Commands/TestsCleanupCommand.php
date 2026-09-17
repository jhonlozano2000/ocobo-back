<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestsCleanupCommand extends Command
{
    protected $signature = 'tests:cleanup';
    protected $description = 'Limpia datos creados por tests (usuarios, radicados, etc.)';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== Limpieza de datos de prueba ===');
        $this->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = [
            'ventanilla_radica_reci_pase_historial',
            'ventanilla_radica_reci_compartir_historial',
            'ventanilla_radica_reci_responsa',
            'ventanilla_radica_reci',
            'ventanilla_radica_enviados',
            'ventanilla_radica_internos_responsa',
            'ventanilla_radica_internos',
            'clasificacion_documental_trd',
            'calidad_organigrama',
            'users_cargos',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
        ];

        foreach ($tables as $table) {
            $deleted = DB::table($table)->delete();
            if ($deleted > 0) {
                $this->line("  {$table}: {$deleted} eliminados", 'fg=yellow');
            }
        }

        $deletedUsers = DB::table('users')->where('id', '!=', 2)->delete();
        if ($deletedUsers > 0) {
            $this->line("  users: {$deletedUsers} eliminados", 'fg=yellow');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->newLine();
        $this->info('Conteo final:');
        $this->line('  Users: ' . DB::table('users')->count());
        $this->line('  users_cargos: ' . DB::table('users_cargos')->count());
        $this->line('  TRD: ' . DB::table('clasificacion_documental_trd')->count());
        $this->line('  Organigrama: ' . DB::table('calidad_organigrama')->count());

        $this->newLine();
        $this->info('Limpieza completada.');

        return self::SUCCESS;
    }
}
