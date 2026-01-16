<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega índices para optimizar las consultas de relaciones
     */
    public function up(): void
    {
        // Índice para archivos adicionales
        if (!$this->indexExists('ventanilla_radica_reci_archivos', 'idx_vrr_archivos_radicado_id')) {
            DB::statement("CREATE INDEX idx_vrr_archivos_radicado_id ON ventanilla_radica_reci_archivos(radicado_id)");
        }

        // Índices para responsables
        if (!$this->indexExists('ventanilla_radica_reci_responsa', 'idx_vrr_responsa_radicado_id')) {
            DB::statement("CREATE INDEX idx_vrr_responsa_radicado_id ON ventanilla_radica_reci_responsa(radica_reci_id)");
        }

        if (!$this->indexExists('ventanilla_radica_reci_responsa', 'idx_vrr_responsa_users_cargos_id')) {
            DB::statement("CREATE INDEX idx_vrr_responsa_users_cargos_id ON ventanilla_radica_reci_responsa(users_cargos_id)");
        }

        // Índice compuesto para custodios (optimiza filtros frecuentes)
        if (!$this->indexExists('ventanilla_radica_reci_responsa', 'idx_vrr_responsa_radicado_custodio')) {
            DB::statement("CREATE INDEX idx_vrr_responsa_radicado_custodio ON ventanilla_radica_reci_responsa(radica_reci_id, custodio)");
        }

        // Índice para users_cargos (si no existe ya)
        if (!$this->indexExists('users_cargos', 'idx_users_cargos_user_cargo')) {
            DB::statement("CREATE INDEX idx_users_cargos_user_cargo ON users_cargos(user_id, cargo_id)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("DROP INDEX idx_vrr_archivos_radicado_id ON ventanilla_radica_reci_archivos");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }

        try {
            DB::statement("DROP INDEX idx_vrr_responsa_radicado_id ON ventanilla_radica_reci_responsa");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }

        try {
            DB::statement("DROP INDEX idx_vrr_responsa_users_cargos_id ON ventanilla_radica_reci_responsa");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }

        try {
            DB::statement("DROP INDEX idx_vrr_responsa_radicado_custodio ON ventanilla_radica_reci_responsa");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }

        try {
            DB::statement("DROP INDEX idx_users_cargos_user_cargo ON users_cargos");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
    }

    /**
     * Verifica si un índice existe en una tabla
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND index_name = ?
        ", [$table, $indexName]);

        return $result[0]->count > 0;
    }
};