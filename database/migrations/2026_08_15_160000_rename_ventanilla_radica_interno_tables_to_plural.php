<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renombrar tablas de singular a plural
        $renames = [
            'ventanilla_radica_interno_archivos' => 'ventanilla_radica_internos_archivos',
            'ventanilla_radica_interno_responsa' => 'ventanilla_radica_internos_responsa',
            'ventanilla_radica_interno_proyectores' => 'ventanilla_radica_internos_proyectores',
            'ventanilla_radica_interno_metadata' => 'ventanilla_radica_internos_metadata',
            'ventanilla_radica_interno_metadata_history' => 'ventanilla_radica_internos_metadata_history',
            'ventanilla_radica_interno_pase_historial' => 'ventanilla_radica_internos_pase_historial',
            'ventanilla_radica_interno_compartir_historial' => 'ventanilla_radica_internos_compartir_historial',
            'ventanilla_radica_interno_respuestas' => 'ventanilla_radica_internos_respuestas',
        ];

        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        // Recrear la vista con las tablas renombradas
        DB::statement('DROP VIEW IF EXISTS ventanilla_radica_interno_view');
        DB::statement('DROP VIEW IF EXISTS ventanilla_radica_internos_view');

        DB::statement('
            CREATE VIEW ventanilla_radica_internos_view AS
            SELECT
                vri.id,
                vri.num_radicado,
                vri.created_at,
                vri.fec_venci,
                vri.archivo_digital,
                vri.asunto,
                vri.clasifica_documen_id,
                vri.usuario_crea,
                cd.cod as clasificacion_cod,
                cd.nom as clasificacion_nom,
                cd_parent.nom as clasificacion_parent_nom,
                CONCAT(u_crea.nombres, " ", u_crea.apellidos) as usuario_crea_nombre,
                (SELECT COUNT(*) FROM ventanilla_radica_internos_destina WHERE radica_interno_id = vri.id) as total_destinatarios,
                dest.nombres as destinatarios_nombres,
                (SELECT COUNT(*) FROM ventanilla_radica_internos_responsa WHERE radica_interno_id = vri.id) as total_responsables,
                resp.nombres as responsables_nombres,
                (SELECT COUNT(*) FROM ventanilla_radica_internos_proyectores WHERE radica_interno_id = vri.id) as total_proyectores,
                proy.nombres as proyectores_nombres,
                (SELECT COUNT(*) FROM ventanilla_radica_internos_firmantes WHERE radica_interno_id = vri.id) as total_firmantes,
                firm.nombres as firmantes_nombres,
                (SELECT COUNT(*) FROM ventanilla_radica_internos_responsa WHERE radica_interno_id = vri.id AND custodio = 1) as total_custodios
            FROM ventanilla_radica_internos vri
            LEFT JOIN clasificacion_documental_trd cd ON vri.clasifica_documen_id = cd.id
            LEFT JOIN clasificacion_documental_trd cd_parent ON cd.parent = cd_parent.id
            LEFT JOIN users u_crea ON vri.usuario_crea = u_crea.id
            LEFT JOIN (
                SELECT d.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, " ", u.apellidos) SEPARATOR ", ") as nombres
                FROM ventanilla_radica_internos_destina d
                JOIN users_cargos uc ON d.users_cargos_id = uc.id
                JOIN users u ON uc.user_id = u.id
                GROUP BY d.radica_interno_id
            ) dest ON dest.radica_interno_id = vri.id
            LEFT JOIN (
                SELECT r.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, " ", u.apellidos) SEPARATOR ", ") as nombres
                FROM ventanilla_radica_internos_responsa r
                JOIN users_cargos uc ON r.users_cargos_id = uc.id
                JOIN users u ON uc.user_id = u.id
                GROUP BY r.radica_interno_id
            ) resp ON resp.radica_interno_id = vri.id
            LEFT JOIN (
                SELECT p.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, " ", u.apellidos) SEPARATOR ", ") as nombres
                FROM ventanilla_radica_internos_proyectores p
                JOIN users_cargos uc ON p.users_cargos_id = uc.id
                JOIN users u ON uc.user_id = u.id
                GROUP BY p.radica_interno_id
            ) proy ON proy.radica_interno_id = vri.id
            LEFT JOIN (
                SELECT f.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, " ", u.apellidos) SEPARATOR ", ") as nombres
                FROM ventanilla_radica_internos_firmantes f
                JOIN users u ON f.users_id = u.id
                GROUP BY f.radica_interno_id
            ) firm ON firm.radica_interno_id = vri.id
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ventanilla_radica_internos_view');

        $renames = [
            'ventanilla_radica_internos_archivos' => 'ventanilla_radica_interno_archivos',
            'ventanilla_radica_internos_responsa' => 'ventanilla_radica_interno_responsa',
            'ventanilla_radica_internos_proyectores' => 'ventanilla_radica_interno_proyectores',
            'ventanilla_radica_internos_metadata' => 'ventanilla_radica_interno_metadata',
            'ventanilla_radica_internos_metadata_history' => 'ventanilla_radica_interno_metadata_history',
            'ventanilla_radica_internos_pase_historial' => 'ventanilla_radica_interno_pase_historial',
            'ventanilla_radica_internos_compartir_historial' => 'ventanilla_radica_interno_compartir_historial',
            'ventanilla_radica_internos_respuestas' => 'ventanilla_radica_interno_respuestas',
        ];

        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        DB::statement('
            CREATE VIEW ventanilla_radica_interno_view AS
            SELECT * FROM ventanilla_radica_internos
        ');
    }
};
